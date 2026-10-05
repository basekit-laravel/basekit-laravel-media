<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Models;

use BasekitLaravel\BasekitLaravelMedia\Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A file stored on a Laravel filesystem disk, usually the featured picture of a
 * page. The row holds the storage metadata; the blob stays on the disk so it can
 * be served without the application.
 *
 * `delete()` is a soft delete and leaves the blob; `forceDelete()` takes both.
 * See `softDeletesEnabled()` for the setting that makes it permanent instead.
 *
 * @property int $id
 * @property int|null $folder_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $alt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read MediaFolder|null $folder
 */
class Media extends Model
{
    /**
     * @use HasFactory<MediaFactory>
     */
    use HasFactory;

    /**
     * The trait's own delete is aliased because a trait's methods are copied into
     * the class: `parent::performDeleteOnModel()` would reach `Model`'s hard
     * delete and turn every soft delete in the library into a real one.
     */
    use SoftDeletes {
        performDeleteOnModel as performSoftDeleteOnModel;
    }

    protected $table = 'media';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'size' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return Factory<Media>
     */
    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Media $media): void {
            $media->guardAgainstDuplicatePath();
        });

        static::deleting(function (Media $media): void {
            // A soft delete has to leave the file alone, or there is nothing left
            // to restore. Only a delete that takes the row takes the blob with it.
            if ($media->removesFileWithRow()) {
                $media->deleteFile();
            }
        });
    }

    /**
     * Whether deleting a media item is reversible.
     *
     * A trait cannot be applied conditionally, so the column and the trait are
     * always present and this decides what `delete()` does with the row. Keeping
     * the schema unconditional lets the setting change without a migration.
     */
    public static function softDeletesEnabled(): bool
    {
        return (bool) config('basekit-laravel-media.soft_deletes', true);
    }

    /**
     * Whether the row is going away, and the file with it.
     *
     * @return mixed
     */
    protected function performDeleteOnModel()
    {
        if (static::softDeletesEnabled()) {
            return $this->performSoftDeleteOnModel();
        }

        // What the trait does for a force delete, minus the recursion: going back
        // through `forceDelete()` comes straight back here.
        return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function (): void {
            $this->exists = false;
        });
    }

    /**
     * Whether the row is going away, and the file with it.
     */
    protected function removesFileWithRow(): bool
    {
        return $this->isForceDeleting() || ! static::softDeletesEnabled();
    }

    /**
     * Two rows may not claim the same file: whichever page rendered second would
     * show the wrong name and alt text for it.
     *
     * A model check rather than a unique index, because the index cannot hold a
     * trashed row *and* allow its file to be uploaded again, and it fails as an
     * opaque driver error instead of something the form can show.
     *
     * @throws ValidationException
     */
    protected function guardAgainstDuplicatePath(): void
    {
        $clash = static::query()
            ->where('disk', $this->disk)
            ->where('path', $this->path)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if (! $clash) {
            return;
        }

        throw ValidationException::withMessages([
            'path' => __('basekit-laravel-media::basekit-laravel-media.duplicate_path', [
                'path' => $this->path,
            ]),
        ]);
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * The web URL of the file, which is what a page stores as its featured
     * image and what the public views render straight into an <img>.
     */
    public function storageUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Remove the file from its disk. The row is left to the delete that triggered
     * the model, so a bulk delete only touches the file once per row.
     */
    public function deleteFile(): void
    {
        if ($this->path === '') {
            return;
        }

        $disk = Storage::disk($this->disk);

        if ($disk->exists($this->path)) {
            $disk->delete($this->path);
        }
    }

    /**
     * The disk the media library writes to.
     */
    public static function diskName(): string
    {
        return (string) config('basekit-laravel-media.disk', 'public');
    }

    /**
     * The largest upload the uploader accepts, in kilobytes, or `null` for
     * Filament's own default.
     */
    public static function maxUploadSize(): ?int
    {
        $size = config('basekit-laravel-media.uploads.max_size');

        return $size === null ? null : (int) $size;
    }

    /**
     * The file types the uploader accepts.
     *
     * @return array<int, string>
     */
    public static function acceptedFileTypes(): array
    {
        /** @var array<int, string> $types */
        $types = config('basekit-laravel-media.uploads.accepted_file_types', ['image/*']);

        return $types;
    }

    /**
     * The directory uploaded files are stored in, under the folder that was open
     * at upload time.
     */
    public static function uploadDirectory(?MediaFolder $folder = null): string
    {
        $parts = array_values(array_filter([
            trim((string) config('basekit-laravel-media.path', 'media'), '/'),
            $folder?->slug,
        ]));

        return implode('/', $parts);
    }

    /**
     * The media item a consuming model saved by its web URL or storage path,
     * which is how the picker shows the item that was chosen back then.
     *
     * Resolved on the indexed `path` column: this runs once per picker field while
     * a form hydrates, so walking the table would cost a pass over the library for
     * every image on the page.
     */
    public static function findByStorageValue(string $value): ?static
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $match = static::query()
            ->whereIn('path', static::candidatePaths($value))
            ->get()
            ->first(fn (self $media): bool => $media->matchesStorageValue($value));

        if ($match instanceof self) {
            return $match;
        }

        return static::findByUnknownStorageUrl($value);
    }

    /**
     * The `path` values a stored value could be. A consuming model holds the web
     * URL, so stripping the disk's base off it leaves the column value.
     *
     * @return array<int, string>
     */
    protected static function candidatePaths(string $value): array
    {
        $base = rtrim(Storage::disk(static::diskName())->url(''), '/');

        if ($base !== '' && Str::startsWith($value, $base.'/')) {
            return [Str::after($value, $base.'/')];
        }

        return [$value];
    }

    /**
     * A value whose base this package does not recognise, such as a CDN URL
     * written before the disk pointed at that CDN. No `path` can be derived from
     * it, so the rows are compared by the URL each one actually builds.
     */
    protected static function findByUnknownStorageUrl(string $value): ?static
    {
        if (! Str::startsWith($value, ['http://', 'https://', '//', '/'])) {
            return null;
        }

        return static::query()
            ->cursor()
            ->first(fn (self $media): bool => $media->storageUrl() === $value);
    }

    /**
     * Whether this is the item a stored value names.
     */
    protected function matchesStorageValue(string $value): bool
    {
        return $this->path === $value || $this->storageUrl() === $value;
    }
}
