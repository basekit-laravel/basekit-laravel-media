<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Support;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Persists an upload the way the admin submits it: the file is already on its disk
 * by the time the form is saved, so these helpers only fill in the storage metadata
 * the model needs and keep the file in sync with the row.
 */
final class MediaWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Media
    {
        $disk = Media::diskName();
        $path = (string) ($data['path'] ?? '');

        $data['disk'] = $disk;
        $data['path'] = $path;
        $data['mime_type'] ??= $this->mimeType($disk, $path);
        $data['size'] ??= $this->size($disk, $path);

        return Media::create($data);
    }

    /**
     * Updates the metadata of a media item. A changed path replaces the stored file;
     * changing the folder is metadata only, so pages that already refer to the file
     * keep working.
     *
     * A `folder_id` or `alt` the caller left out keeps the value already stored —
     * only an explicit `null` clears one, so a caller that came to rename a file
     * does not also lose where it was filed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Media $media, array $data): Media
    {
        $oldPath = $media->path;
        $disk = Media::diskName();
        $path = (string) ($data['path'] ?? $oldPath);

        $media->fill([
            'name' => (string) ($data['name'] ?? $media->name),
            'alt' => $this->nullableString($data['alt'] ?? $media->alt),
            'folder_id' => $this->folderId($data, $media),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $path === $oldPath ? $media->mime_type : $this->mimeType($disk, $path),
            'size' => $path === $oldPath ? $media->size : $this->size($disk, $path),
        ]);

        $media->save();

        $disk = Storage::disk($disk);

        if ($path !== $oldPath && $oldPath !== '' && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        return $media;
    }

    /**
     * The folder an update files the item under: the one the caller named, the one
     * it is already in, or none when the caller explicitly cleared it.
     *
     * @param  array<string, mixed>  $data
     */
    private function folderId(array $data, Media $media): ?int
    {
        if (array_key_exists('folder_id', $data)) {
            return $data['folder_id'] === null ? null : (int) $data['folder_id'];
        }

        return $media->folder_id === null ? null : (int) $media->folder_id;
    }

    private function mimeType(string $disk, string $path): ?string
    {
        $disk = Storage::disk($disk);

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path);

        return $mime === false ? null : (string) $mime;
    }

    private function size(string $disk, string $path): int
    {
        $disk = Storage::disk($disk);

        if ($path === '' || ! $disk->exists($path)) {
            return 0;
        }

        return (int) $disk->size($path);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
