<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Support;

use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The folder hierarchy flattened into drawing order, plus the views of it the
 * admin screen needs: the indented options of the *Move* form and the ancestors
 * of a folder for the breadcrumb.
 */
final class FolderTree
{
    /**
     * The group key a top-level folder hangs off, chosen so it cannot collide
     * with a folder ID.
     */
    private const string ROOT = 'root';

    /**
     * @var array<int, array{folder: MediaFolder, depth: int}>|null
     */
    private ?array $branches = null;

    /**
     * Every folder in drawing order paired with its depth, so a caller walks the
     * tree with one loop and no recursion.
     *
     * @return array<int, array{folder: MediaFolder, depth: int}>
     */
    public function branches(): array
    {
        if ($this->branches !== null) {
            return $this->branches;
        }

        $folders = MediaFolder::query()
            ->withCount('media')
            ->orderBy('name')
            ->get();

        $byParent = $folders->groupBy(fn (MediaFolder $folder): string => (string) ($folder->parent_id ?? self::ROOT));

        $tree = [];
        $visited = [];

        $append = function (?int $parentId, int $depth) use (&$append, &$tree, &$visited, $byParent): void {
            foreach ($byParent->get((string) ($parentId ?? self::ROOT)) ?? [] as $folder) {
                if (isset($visited[$folder->getKey()])) {
                    continue;
                }

                $visited[$folder->getKey()] = true;
                $tree[] = ['folder' => $folder, 'depth' => $depth];

                $append($folder->getKey(), $depth + 1);
            }
        };

        $append(null, 0);

        // A folder whose parent chain never reaches the top would never be
        // drawn, so the leftovers go on top.
        foreach ($folders as $folder) {
            if (isset($visited[$folder->getKey()])) {
                continue;
            }

            $visited[$folder->getKey()] = true;
            $tree[] = ['folder' => $folder, 'depth' => 0];

            $append($folder->getKey(), 1);
        }

        return $this->branches = $tree;
    }

    /**
     * The tree as `id => name`, indented by depth so a folder reads as nested in
     * the dropdown of the *Move* action.
     *
     * @return array<int|string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->branches() as ['folder' => $folder, 'depth' => $depth]) {
            $options[$folder->getKey()] = str_repeat('— ', $depth).$folder->name;
        }

        return $options;
    }

    /**
     * A folder's ancestors with the folder itself last, top of the library first.
     *
     * @return array<int, MediaFolder>
     */
    public function trail(?MediaFolder $folder): array
    {
        $trail = [];
        $walked = [];

        while ($folder instanceof MediaFolder) {
            if (isset($walked[$folder->getKey()])) {
                break;
            }

            $walked[$folder->getKey()] = true;
            array_unshift($trail, $folder);

            $folder = $folder->parent_id === null
                ? null
                : MediaFolder::query()->find($folder->parent_id);
        }

        return $trail;
    }

    /**
     * The folders filed under `$parentId`, or the top level of the library when
     * it is `null`.
     *
     * @return Collection<int, MediaFolder>
     */
    public function children(?int $parentId): Collection
    {
        return MediaFolder::query()
            ->withCount('media')
            ->when(
                $parentId === null,
                fn (Builder $query): Builder => $query->whereNull('parent_id'),
                fn (Builder $query): Builder => $query->where('parent_id', $parentId),
            )
            ->orderBy('name')
            ->get();
    }
}
