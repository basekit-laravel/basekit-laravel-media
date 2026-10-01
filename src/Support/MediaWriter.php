<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Support;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Persists a media upload the way the admin submits it: the file is already
 * on its disk by the time the form is saved, so these helpers only fill in the
 * storage metadata the model needs and keep the storage file in sync with the
 * database row.
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
     * Updates the metadata of a media item. A new upload (a changed path)
     * replaces the stored file; changing the folder only moves the metadata,
     * never the file, so pages that already reference the file keep working.
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
            'alt' => $this->nullableString($data['alt'] ?? null),
            'folder_id' => $data['folder_id'] ?? null,
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
