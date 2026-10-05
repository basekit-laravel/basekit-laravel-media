<?php

declare(strict_types=1);

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('refuses two media items claiming the same file on the same disk', function (): void {
    $media = makeMedia(['path' => 'media/photo.jpg']);

    expect(fn (): Media => Media::factory()->create(['path' => 'media/photo.jpg']))
        ->toThrow(ValidationException::class);
});

it('lets the same path through on another disk', function (): void {
    makeMedia(['path' => 'media/photo.jpg']);

    config(['filesystems.disks.other' => config('filesystems.disks.testing')]);

    Storage::disk('other')->put('media/photo.jpg', 'photo');

    expect(Media::factory()->create(['path' => 'media/photo.jpg', 'disk' => 'other'])->disk)
        ->toBe('other');
});

it('lets a media item keep its own path while it is being saved', function (): void {
    $media = makeMedia(['path' => 'media/photo.jpg']);

    $media->update(['alt' => 'A different description of the same file']);

    expect($media->fresh()?->alt)->toBe('A different description of the same file')
        ->and(Media::query()->count())->toBe(1);
});

it('frees the path again once the item holding it is deleted', function (): void {
    $media = makeMedia(['path' => 'media/photo.jpg']);

    $media->delete();

    Storage::disk('testing')->put('media/photo.jpg', 'the file that replaces it');

    expect(Media::factory()->create(['path' => 'media/photo.jpg'])->path)->toBe('media/photo.jpg');
});

it('leaves the file on the disk when a media item is deleted, and takes it away when the deletion is made final', function (): void {
    Storage::disk('testing')->put('media/photo.jpg', 'photo');

    $media = makeMedia(['path' => 'media/photo.jpg']);

    $media->delete();

    expect(Media::query()->count())->toBe(0)
        ->and(Media::withTrashed()->count())->toBe(1)
        ->and(Storage::disk('testing')->exists('media/photo.jpg'))->toBeTrue();

    $trashed = Media::withTrashed()->findOrFail($media->getKey());

    $trashed->restore();

    expect(Media::query()->count())->toBe(1)
        ->and($trashed->fresh()?->deleted_at)->toBeNull()
        ->and(Storage::disk('testing')->exists('media/photo.jpg'))->toBeTrue();

    $trashed->forceDelete();

    expect(Media::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('testing')->exists('media/photo.jpg'))->toBeFalse();
});

it('does not let a folder be filed inside itself through a longer chain', function (): void {
    $year = makeFolder('2026');
    $fees = makeFolder('Fees', ['parent_id' => $year->getKey()]);
    $invoice = makeFolder('Invoice', ['parent_id' => $fees->getKey()]);

    $fees->update(['parent_id' => $invoice->getKey()]);

    expect($fees->fresh()?->parent_id)->toBeNull();
});

it('takes a second folder of the same name apart rather than letting the second one fail', function (): void {
    makeFolder('Fees');

    $second = MediaFolder::createWithUniqueSlug(['name' => 'Fees']);

    expect($second->slug)->not->toBe('fees')
        ->and(MediaFolder::query()->where('slug', $second->slug)->count())->toBe(1);
});

it('reads the upload limits an application has configured', function (): void {
    config([
        'basekit-laravel-media.uploads.max_size' => 2048,
        'basekit-laravel-media.uploads.accepted_file_types' => ['image/*', 'application/pdf'],
    ]);

    expect(Media::maxUploadSize())->toBe(2048)
        ->and(Media::acceptedFileTypes())->toBe(['image/*', 'application/pdf']);
});

it('has no upload limit of its own unless the application sets one', function (): void {
    config(['basekit-laravel-media.uploads.max_size' => null]);

    expect(Media::maxUploadSize())->toBeNull();
});

it('leaves a deleted row behind while the application has soft deletes on', function (): void {
    config(['basekit-laravel-media.soft_deletes' => true]);

    Storage::disk('testing')->put('media/photo.jpg', 'photo');
    $media = makeMedia(['path' => 'media/photo.jpg']);

    expect(Media::softDeletesEnabled())->toBeTrue();

    $media->delete();

    expect(Media::query()->count())->toBe(0)
        ->and(Media::withTrashed()->count())->toBe(1)
        ->and(Storage::disk('testing')->exists('media/photo.jpg'))->toBeTrue();
});

it('takes the row and the file together once the application switches soft deletes off', function (): void {
    config(['basekit-laravel-media.soft_deletes' => false]);

    Storage::disk('testing')->put('media/photo.jpg', 'photo');
    $media = makeMedia(['path' => 'media/photo.jpg']);

    expect(Media::softDeletesEnabled())->toBeFalse();

    $media->delete();

    expect(Media::query()->count())->toBe(0)
        ->and(Media::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('testing')->exists('media/photo.jpg'))->toBeFalse();
});

it('still refuses two files claiming the same path once soft deletes are off', function (): void {
    config(['basekit-laravel-media.soft_deletes' => false]);

    makeMedia(['path' => 'media/photo.jpg']);

    expect(fn (): Media => Media::factory()->create(['path' => 'media/photo.jpg']))
        ->toThrow(ValidationException::class);
});

it('leaves nothing to restore when soft deletes are off', function (): void {
    config(['basekit-laravel-media.soft_deletes' => false]);

    $media = makeMedia();
    $media->delete();

    // The row is gone, so there is nothing left for `restore()` to bring back.
    // It answers rather than throws, which is what an application asking to undo
    // a deletion that cannot be undone should be able to check for.
    expect(Media::withTrashed()->count())->toBe(0)
        ->and($media->forceDelete())->toBeNull();
});

it('keeps the folder a file is filed in when an update says nothing about it', function (): void {
    $folder = makeFolder('Fees');
    $media = makeMedia(['folder_id' => $folder->getKey(), 'alt' => 'A receipt']);

    $written = app(MediaWriter::class)->update($media, ['name' => 'receipt.jpg']);

    expect($written->folder_id)->toBe($folder->getKey())
        ->and($written->alt)->toBe('A receipt');
});

it('unfiles a file when an update says the folder is nothing', function (): void {
    $folder = makeFolder('Fees');
    $media = makeMedia(['folder_id' => $folder->getKey()]);

    expect(app(MediaWriter::class)->update($media, ['folder_id' => null])->folder_id)->toBeNull();
});

it('moves a file when an update names another folder', function (): void {
    $from = makeFolder('Fees');
    $to = makeFolder('Archive');
    $media = makeMedia(['folder_id' => $from->getKey()]);

    expect(app(MediaWriter::class)->update($media, ['folder_id' => $to->getKey()])->folder_id)
        ->toBe($to->getKey());
});

it('leaves the unique index on the folder slug to the database, which is what the retry above answers to', function (): void {
    $existing = makeFolder('Fees');

    // The model checks for a taken slug before inserting, so a collision only
    // reaches the index when the row arrives from somewhere else: an import, a
    // second request that read the table first, or an older release. The index is
    // what still holds the line, and what `createWithUniqueSlug()` retries on.
    expect(fn (): bool => DB::table('media_folders')->insert([
        'name' => 'Fees',
        'slug' => $existing->slug,
        'parent_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
