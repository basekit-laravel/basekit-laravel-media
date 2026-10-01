<?php

declare(strict_types=1);

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('exposes the web url of a stored file', function (): void {
    $media = makeMedia(['path' => 'media/photo.jpg']);

    expect($media->storageUrl())->toBe('/storage/media/photo.jpg');
});

it('finds a media item by its stored url or path', function (): void {
    $media = makeMedia(['path' => 'media/photo.jpg']);

    expect(Media::findByStorageValue('/storage/media/photo.jpg')?->is($media))->toBeTrue()
        ->and(Media::findByStorageValue('media/photo.jpg')?->is($media))->toBeTrue()
        ->and(Media::findByStorageValue('media/other.jpg'))->toBeNull();
});

it('stores an uploaded file with its storage metadata on create', function (): void {
    Storage::disk(Media::diskName());

    $path = Storage::disk('testing')->putFile('media', UploadedFile::fake()->image('cover.jpg'));

    $media = app(MediaWriter::class)->create([
        'path' => $path,
        'name' => 'cover.jpg',
        'alt' => 'A cover',
    ]);

    expect($media->disk)->toBe('testing')
        ->and($media->path)->toBe($path)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->name)->toBe('cover.jpg')
        ->and($media->alt)->toBe('A cover');
});

it('replaces the stored file when the path changes on update', function (): void {
    $disk = Storage::disk('testing');

    $disk->put('media/old.jpg', 'old');
    $disk->put('media/new.jpg', 'new');

    $media = makeMedia(['path' => 'media/old.jpg']);

    app(MediaWriter::class)->update($media, ['path' => 'media/new.jpg']);

    expect($media->fresh()->path)->toBe('media/new.jpg')
        ->and($disk->exists('media/old.jpg'))->toBeFalse()
        ->and($disk->exists('media/new.jpg'))->toBeTrue();
});

it('keeps the stored file when only the folder changes', function (): void {
    $disk = Storage::disk('testing');
    $disk->put('media/photo.jpg', 'photo');

    $folder = makeFolder('Fees');

    $media = app(MediaWriter::class)->update(makeMedia(['path' => 'media/photo.jpg']), [
        'folder_id' => $folder->getKey(),
    ]);

    expect($media->fresh()->folder_id)->toBe($folder->getKey())
        ->and($media->fresh()->path)->toBe('media/photo.jpg')
        ->and($disk->exists('media/photo.jpg'))->toBeTrue();
});

it('deletes the stored file when a media item is deleted', function (): void {
    $disk = Storage::disk('testing');
    $disk->put('media/photo.jpg', 'photo');

    $media = makeMedia(['path' => 'media/photo.jpg']);

    $media->delete();

    expect($disk->exists('media/photo.jpg'))->toBeFalse()
        ->and(Media::query()->count())->toBe(0);
});

it('fills metadata for a file put on the disk outside the uploader', function (): void {
    Storage::disk('testing')->put('media/notes.txt', 'hello');

    $media = app(MediaWriter::class)->create([
        'path' => 'media/notes.txt',
        'name' => 'notes.txt',
    ]);

    expect($media->mime_type)->toBe('text/plain')
        ->and($media->size)->toBe(5);
});

it('generates a unique slug for a folder name', function (): void {
    $first = makeFolder('Fees');
    $second = makeFolder('Fees');

    expect($first->slug)->toBe('fees')
        ->and($second->slug)->toBe('fees-2');
});

it('refuses a folder whose parent is itself', function (): void {
    $folder = makeFolder('Fees');

    $folder->parent_id = $folder->getKey();
    $folder->save();

    expect($folder->fresh()->parent_id)->toBeNull();
});

it('builds the upload directory from the configured path and the folder slug', function (): void {
    $folder = makeFolder('Fees');

    expect(Media::uploadDirectory($folder))->toBe('media/fees')
        ->and(Media::uploadDirectory())->toBe('media');
});
