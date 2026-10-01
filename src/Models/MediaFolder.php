<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Models;

use BasekitLaravel\BasekitLaravelMedia\Database\Factories\MediaFolderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A folder organizes media files without duplicating them. Folders can nest,
 * and a media file belongs to at most one folder. The folder's slug is only
 * used as the upload directory of future files; renaming a folder never moves
 * files that are already stored, so published URLs stay stable.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaFolder|null $parent
 * @property-read Collection<int, MediaFolder> $children
 * @property-read Collection<int, Media> $media
 */
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use HasFactory;

    protected $table = 'media_folders';

    protected $guarded = [
        'id',
    ];

    /**
     * @return Factory<MediaFolder>
     */
    protected static function newFactory(): Factory
    {
        return MediaFolderFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (MediaFolder $folder): void {
            $folder->slug ??= self::uniqueSlug($folder->name);
        });

        static::saving(function (MediaFolder $folder): void {
            // A folder can never be its own parent. Guarding here keeps the
            // constraint even when the value comes from somewhere other than
            // the form.
            if ($folder->parent_id !== null && (int) $folder->parent_id === $folder->id) {
                $folder->parent_id = null;
            }
        });
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /**
     * A unique slug for a folder name. The first folder of a name keeps the
     * slug, later ones get a numeric suffix.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
