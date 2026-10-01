<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Models;

use BasekitLaravel\BasekitLaravelMedia\Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A media file stored on a Laravel filesystem disk, usually an image chosen
 * as the featured picture of a page. The database keeps the storage metadata,
 * and the file itself lives on the configured disk so it can be served by the
 * web server without the application.
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
 * @property-read MediaFolder|null $folder
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'size' => 'integer',
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
        static::deleting(function (Media $media): void {
            $media->deleteFile();
        });
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
     * Remove the file from its disk. The database row is left to the delete
     * call that triggered the model, so a bulk delete only touches the file
     * once per model.
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
     * The directory uploaded files are stored in, grouping them by the folder
     * chosen at upload time under the configured media path.
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
     * The media item whose stored value — its web URL or storage path — matches
     * a value saved on a consuming model (such as a featured image), so the
     * picker can show the item that was chosen back then.
     */
    public static function findByStorageValue(string $value): ?static
    {
        foreach (static::query()->cursor() as $media) {
            if ($media->storageUrl() === $value || $media->path === $value) {
                return $media;
            }
        }

        return null;
    }
}
