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
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A folder files media under without duplicating it. Folders nest, and a file
 * belongs to at most one.
 *
 * The slug is only the upload directory of *future* files — renaming a folder
 * never moves files that are already stored, so published URLs stay stable.
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
            // A folder can never sit inside itself, directly or by a longer road: the
            // tree is walked by following parent links, so a cycle would leave the walk
            // going for ever. Guarding here keeps the constraint even when the value
            // comes from somewhere other than a form.
            if (self::wouldCreateCycle($folder, $folder->parent_id)) {
                $folder->parent_id = null;
            }
        });
    }

    /**
     * A folder as a child of the given parent, saving again only if the parent the
     * slug was taken for no longer holds it.
     *
     * Two folders created from the same name at the same moment can pick the same
     * slug and the second insert loses to the unique index; retrying finds the one
     * the first insert just took.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithUniqueSlug(array $attributes = []): static
    {
        $attempts = 5;

        while (true) {
            try {
                return static::query()->create($attributes);
            } catch (QueryException $exception) {
                if (--$attempts < 1 || ! self::isSlugCollision($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Whether making `$folder` a child of `$parentId` would put it inside itself.
     */
    protected static function wouldCreateCycle(self $folder, mixed $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        // A folder that is not written yet has no key, so there is nothing it can
        // already be inside of.
        $key = $folder->getKey();

        if ($key === null) {
            return false;
        }

        $key = (int) $key;

        $walked = [];
        $current = (int) $parentId;

        while (true) {
            // Reaching the folder is the cycle. Meeting a folder twice is a chain
            // that already loops, which is no place to hang one more folder.
            if ($current === $key || isset($walked[$current])) {
                return true;
            }

            $walked[$current] = true;

            $next = static::query()->find($current)?->parent_id;

            if ($next === null) {
                return false;
            }

            $current = (int) $next;
        }
    }

    /**
     * Whether the failure was the unique index on `slug` rather than anything else
     * the insert could have run into.
     */
    protected static function isSlugCollision(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 19
            || str_contains(strtolower($exception->getMessage()), 'unique');
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
     * A unique slug for a folder name: the first folder of a name keeps the slug,
     * later ones get a numeric suffix.
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
