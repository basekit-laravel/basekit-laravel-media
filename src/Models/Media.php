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
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A file stored on a Laravel filesystem disk: the row holds the metadata and the
 * blob stays on the disk. `delete()` is a soft delete and leaves the blob;
 * `forceDelete()` takes both.
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
     * Reaches the trait's own delete: `parent::` would land on `Model`'s hard
     * delete instead, because trait methods are copied into the class.
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
            // A soft delete leaves the file behind, which is what makes
            // restoring it possible.
            if ($media->removesFileWithRow()) {
                $media->deleteFile();
            }
        });
    }

    /**
     * Whether deleting a media item is reversible.
     */
    public static function softDeletesEnabled(): bool
    {
        return (bool) config('basekit-laravel-media.soft_deletes', true);
    }

    /**
     * The delete the trait would make, or a real one when soft deletes are off.
     *
     * @return mixed
     */
    protected function performDeleteOnModel()
    {
        if (static::softDeletesEnabled()) {
            return $this->performSoftDeleteOnModel();
        }

        // The trait would call `forceDelete()`, which would come straight back
        // here; this is that same delete without the recursion.
        return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function (): void {
            $this->exists = false;
        });
    }

    /**
     * Whether this delete takes the file with the row.
     */
    protected function removesFileWithRow(): bool
    {
        return $this->isForceDeleting() || ! static::softDeletesEnabled();
    }

    /**
     * Refuses a second row for the same disk file, so the two cannot show
     * different names and alt text for one upload.
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
     * The web URL of the file, which is what a page stores as its featured image.
     */
    public function storageUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * The file size as an editor reads it, for the tile and the edit preview.
     */
    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }

    /**
     * Remove the file from its disk. The row is left to the delete that
     * triggered it.
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
     * The directory uploads land in, under the folder open at upload time.
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
     * The media item a consuming model saved, by web URL or storage path.
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
     * The `path` values a stored value could be: a model holds the web URL, so
     * stripping the disk's base off it leaves the column value.
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
     * The item whose own URL matches, for a stored value no `path` can be
     * derived from, such as one written before the disk pointed at it.
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
