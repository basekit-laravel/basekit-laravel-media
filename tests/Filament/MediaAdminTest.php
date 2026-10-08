<?php

declare(strict_types=1);

use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker;
use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPlugin;
use BasekitLaravel\BasekitLaravelMedia\Filament\Pages\MediaLibrary;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('media'));
});

it('registers the media library page on the panel', function (): void {
    expect((new MediaPlugin)->getId())->toBe('basekit-laravel-media')
        ->and(Filament::getPanel('media')->getPages())->toContain(MediaLibrary::class);
});

it('shows the folder tree and every file when no folder is open', function (): void {
    $folder = makeFolder('Fees');
    $filed = makeMedia(['name' => 'filed.jpg', 'folder_id' => $folder->getKey()]);
    $loose = makeMedia(['name' => 'loose.jpg']);

    mediaPage()
        ->assertSuccessful()
        ->assertSee('Fees')
        ->assertSee('filed.jpg')
        ->assertSee('loose.jpg')
        ->assertSee($filed->storageUrl(), escape: false)
        ->assertSee($loose->storageUrl(), escape: false);
});

it('shows only the files of the folder that is open', function (): void {
    $folder = makeFolder('Fees');
    makeMedia(['name' => 'filed.jpg', 'folder_id' => $folder->getKey()]);
    makeMedia(['name' => 'loose.jpg']);

    mediaPage()
        ->set('folder', $folder->getKey())
        ->assertSee('filed.jpg')
        ->assertDontSee('loose.jpg');
});

it('filters the open folder by the search term', function (): void {
    $folder = makeFolder('Fees');
    makeMedia(['name' => 'cover.jpg', 'folder_id' => $folder->getKey()]);
    makeMedia(['name' => 'receipt.jpg', 'folder_id' => $folder->getKey()]);

    mediaPage()
        ->set('folder', $folder->getKey())
        ->set('search', 'receipt')
        ->assertSee('receipt.jpg')
        ->assertDontSee('cover.jpg');
});

it('opens a folder and comes back out of it again', function (): void {
    $folder = makeFolder('Fees');

    $page = mediaPage()
        ->call('selectFolder', $folder->getKey())
        ->assertSet('folder', $folder->getKey());

    $page->call('selectFolder', null)->assertSet('folder', null);
});

it('uploads a file into the folder that is open', function (): void {
    $folder = makeFolder('Fees');

    mediaPage()
        ->set('folder', $folder->getKey())
        ->mountAction('upload')
        ->fillForm([
            'path' => UploadedFile::fake()->image('cover.jpg', 64, 64),
        ])
        ->callMountedAction();

    $media = Media::query()->first();

    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('cover.jpg')
        ->and($media->folder_id)->toBe($folder->getKey())
        ->and(Storage::disk(Media::diskName())->exists($media->path))->toBeTrue()
        ->and($media->path)->toStartWith('media/'.$folder->slug.'/');
});

it('uploads a file at the top of the library when no folder is open', function (): void {
    mediaPage()
        ->mountAction('upload')
        ->fillForm([
            'path' => UploadedFile::fake()->image('cover.jpg', 64, 64),
        ])
        ->callMountedAction();

    expect(Media::query()->first()?->folder_id)->toBeNull();
});

it('creates a folder inside the folder that is open and walks into it', function (): void {
    $parent = makeFolder('Fees');

    $page = mediaPage()
        ->set('folder', $parent->getKey())
        ->mountAction('newFolder')
        ->fillForm(['name' => '2026'])
        ->callMountedAction();

    $folder = MediaFolder::query()->where('name', '2026')->first();

    expect($folder)->not->toBeNull()
        ->and($folder->parent_id)->toBe($parent->getKey());

    $page->assertSet('folder', $folder->getKey());
});

it('files a dropped media item into a folder and leaves the file where it is', function (): void {
    $folder = makeFolder('Fees');
    $media = makeMedia();
    $path = $media->path;

    mediaPage()
        ->call('moveMedia', $media->getKey(), $folder->getKey())
        ->assertHasNoErrors();

    expect($media->fresh()?->folder_id)->toBe($folder->getKey())
        ->and($media->fresh()?->path)->toBe($path)
        ->and(Storage::disk(Media::diskName())->exists($path))->toBeTrue();
});

it('unfiles a media item dropped on the whole library', function (): void {
    $folder = makeFolder('Fees');
    $media = makeMedia(['folder_id' => $folder->getKey()]);

    mediaPage()->call('moveMedia', $media->getKey(), null);

    expect($media->fresh()?->folder_id)->toBeNull();
});

it('ignores a drop that carries no media item or names one that is gone', function (): void {
    mediaPage()
        ->call('moveMedia', null, null)
        ->call('moveMedia', 404, null)
        ->assertHasNoErrors();

    expect(Media::query()->count())->toBe(0);
});

it('unfiles a media item dropped on a folder that is gone rather than failing', function (): void {
    $media = makeMedia(['folder_id' => makeFolder('Fees')->getKey()]);

    mediaPage()->call('moveMedia', $media->getKey(), 404);

    expect($media->fresh()?->folder_id)->toBeNull();
});

it('renders the action modals, so a mounted action has somewhere to open', function (): void {
    makeMedia();

    // A page view that does not go through `x-filament::page` has no modal
    // container, and every action on it then mounts into nothing.
    mediaPage()
        ->assertSeeHtml('wire:partial="action-modals"')
        ->assertSeeHtml('filamentActionModals');
});

it('wires the buttons on a tile to that item rather than to nothing', function (): void {
    $media = makeMedia(['name' => 'cover.jpg']);

    $html = mediaPage()->html();

    foreach (['edit', 'move', 'delete'] as $action) {
        expect(mountedActionArguments($html, $action))->toBe([['id' => $media->getKey()]]);
    }
});

it('wires the delete button on a folder to that folder', function (): void {
    $folder = makeFolder('Fees');

    $html = mediaPage()->html();

    // The folder is in the sidebar and in the grid, so there is more than one
    // button for it and every one of them has to name it.
    $arguments = mountedActionArguments($html, 'deleteFolder');

    expect($arguments)->not->toBeEmpty()
        ->and(array_values(array_unique(array_column($arguments, 'id'))))->toBe([$folder->getKey()]);
});

it('says in the confirmation that a deletion cannot be undone once soft deletes are off', function (): void {
    config(['basekit-laravel-media.soft_deletes' => false]);

    $media = makeMedia(['name' => 'cover.jpg']);

    $component = mediaPage()->mountAction('delete', ['id' => $media->getKey()]);

    // The wording promises a restore the application no longer has, so it has to
    // follow the setting rather than always describing the softer default.
    expect(mountedAction($component)->getModalDescription())->toBe(
        'The file is removed from the library and from the disk. Nothing that refers to it will keep working, and it cannot be put back.',
    );
});

it('confirms a deletion in a modal rather than in a browser dialog', function (): void {
    $media = makeMedia(['name' => 'cover.jpg']);
    $folder = makeFolder('Fees');

    $component = mediaPage();

    expect($component->instance()->deleteAction()->isConfirmationRequired())->toBeTrue()
        ->and($component->instance()->deleteFolderAction()->isConfirmationRequired())->toBeTrue();

    // The modal body is served through its own Livewire partial, so what to check
    // is the action the page mounted, not the markup of the page itself.
    $component->mountAction('delete', ['id' => $media->getKey()]);

    expect(mountedAction($component)->getModalHeading())->toBe('Delete cover.jpg?')
        ->and(mountedAction($component)->getModalDescription())->toBe('The file leaves the library but stays on the disk, so it can be put back. Anything that refers to it will break in the meantime.');

    // A second action is mounted alongside the first rather than replacing it, so
    // the folder is confirmed on a component of its own.
    $folders = mediaPage()
        ->mountAction('deleteFolder', ['id' => $folder->getKey()]);

    expect(mountedAction($folders)->getModalHeading())->toBe('Delete Fees?');

    // Nothing may fall back to `window.confirm()`, which the panel cannot style
    // and which names no file.
    expect($component->html())->not->toContain('confirm(');
});

it('edits the metadata of a media item from its tile', function (): void {
    $media = makeMedia(['name' => 'cover.jpg', 'alt' => 'Original alt']);

    mediaPage()
        ->mountAction('edit', ['id' => $media->getKey()])
        ->assertSchemaStateSet(['name' => 'cover.jpg', 'alt' => 'Original alt'])
        ->fillForm(['name' => 'Renamed.jpg', 'alt' => 'Replacement alt'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($media->fresh()?->name)->toBe('Renamed.jpg')
        ->and($media->fresh()?->alt)->toBe('Replacement alt');
});

it('shows the file itself in the edit modal, beside the fields that describe it', function (): void {
    $folder = makeFolder('Photos');
    $media = makeMedia(['name' => 'cover.jpg', 'folder_id' => $folder->getKey()]);

    // A name and an alt text cannot be written against a filename, so the modal
    // has to show the file they are about.
    expect(actionModalContent(mediaPage(), 'edit', ['id' => $media->getKey()]))
        ->toContain('bkm-edit-preview')
        ->toContain($media->storageUrl())
        ->toContain('1 KB')
        ->toContain('Photos');
});

it('opens the edit form when the image of a tile is clicked', function (): void {
    $media = makeMedia(['name' => 'cover.jpg']);

    $html = mediaPage()->html();

    // The image is the way in to a file, and it names itself for anyone who cannot
    // see it. The click goes through Alpine because it shares a handler with the
    // guard that keeps a drag from counting as a click.
    expect($html)->toContain('class="bkm-tile__preview"')
        ->and($html)->toContain('$wire.mountAction(\'edit\', { id: '.$media->getKey().' })')
        ->and($html)->toContain('aria-label="Preview: cover.jpg"');

    // A drag files the file somewhere else, so the click that a browser may fire
    // at the end of one must not open the form on top of it.
    expect($html)->toContain('justDragged = true')
        ->and($html)->toContain('x-on:pointerdown="justDragged = false"')
        ->and($html)->toContain('if (! justDragged)');

    mediaPage()
        ->call('mountAction', 'edit', ['id' => $media->getKey()])
        ->assertSchemaStateSet(['name' => 'cover.jpg']);
});

it('leaves the tile controls visible rather than only on hover', function (): void {
    makeMedia(['name' => 'cover.jpg']);
    makeFolder('Photos');

    $html = mediaPage()->html();
    $css = file_get_contents(__DIR__.'/../../resources/css/basekit-laravel-media/media-library.css');

    // A control that only exists while the pointer is over its tile is a control
    // nobody finds, so the groups are drawn dimmed and come to full strength on
    // hover rather than starting at nothing. The stylesheet lives beside the
    // package rather than in the view, so the selector it dims by is checked
    // against the class the page actually renders.
    expect($html)->toContain('class="bkm-tile__actions"')
        ->and($css)->toContain('opacity: 0.55')
        ->and($css)->not->toContain('opacity: 0;')
        ->and($css)->toContain('.bkm-tile:hover .bkm-tile__actions');
});

it('moves a media item through the move form and prefills its folder', function (): void {
    $from = makeFolder('Fees');
    $to = makeFolder('Photos');
    $media = makeMedia(['folder_id' => $from->getKey()]);

    mediaPage()
        ->mountAction('move', ['id' => $media->getKey()])
        ->assertSchemaStateSet(['folder_id' => $from->getKey()])
        ->fillForm(['folder_id' => $to->getKey()])
        ->callMountedAction();

    expect($media->fresh()?->folder_id)->toBe($to->getKey());
});

it('deletes a media item from its tile but keeps its file so it can come back', function (): void {
    $media = makeMedia();
    $path = $media->path;

    mediaPage()
        ->mountAction('delete', ['id' => $media->getKey()])
        ->callMountedAction();

    expect(Media::query()->count())->toBe(0)
        ->and(Media::withTrashed()->count())->toBe(1)
        ->and(Storage::disk(Media::diskName())->exists($path))->toBeTrue();
});

it('deletes a folder but keeps the files it held', function (): void {
    $folder = makeFolder('Fees');
    $child = makeFolder('2026', ['parent_id' => $folder->getKey()]);
    $media = makeMedia(['folder_id' => $child->getKey()]);

    mediaPage()
        ->set('folder', $folder->getKey())
        ->mountAction('deleteFolder', ['id' => $folder->getKey()])
        ->callMountedAction()
        ->assertSet('folder', null);

    expect(MediaFolder::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(1)
        ->and($media->fresh()?->folder_id)->toBeNull()
        ->and(Storage::disk(Media::diskName())->exists($media->path))->toBeTrue();
});

it('draws the folders of the open folder as tiles above its files', function (): void {
    $parent = makeFolder('Photos');
    $child = makeFolder('2026', ['parent_id' => $parent->getKey()]);
    makeMedia(['name' => 'cover.jpg', 'folder_id' => $parent->getKey()]);

    // The whole library: the folders at the top of the tree, and the files.
    mediaPage()
        ->assertSeeHtmlInOrder([
            'data-folder-tile="photos"',
            'cover.jpg',
        ]);

    // The open folder: the folders inside it, and its own files. The folder above
    // is no longer a tile, because the page is no longer the whole library.
    mediaPage()
        ->set('folder', $parent->getKey())
        ->assertSeeHtml('data-folder-tile="2026"')
        ->assertSee('cover.jpg')
        ->assertDontSeeHtml('data-folder-tile="photos"');

    expect($child->parent_id)->toBe($parent->getKey());
});

it('does not draw a folder of the open folder as a tile of the whole library', function (): void {
    $parent = makeFolder('Photos');
    makeFolder('2026', ['parent_id' => $parent->getKey()]);

    // The sidebar still lists the whole tree, so only the tiles are a view of
    // what is inside the folder that is open.
    mediaPage()
        ->assertSeeHtml('data-folder="2026"')
        ->assertSeeHtml('data-folder-tile="photos"')
        ->assertDontSeeHtml('data-folder-tile="2026"');
});

it('offers the whole library as the root of the trail', function (): void {
    // With no folder open the whole library is the page the editor is on, so the
    // trail is a single segment and it is not a link to itself.
    $html = mediaPage()->html();

    expect(breadcrumbLabels($html))->toBe(['All files'])
        ->and(breadcrumbLinks($html))->toBe([]);
});

it('walks the trail of the open folder back up to the library', function (): void {
    $parent = makeFolder('Photos');
    $child = makeFolder('2026', ['parent_id' => $parent->getKey()]);

    $html = mediaPage()
        ->set('folder', $child->getKey())
        ->html();

    // Oldest first, and the folder that is open is the current page rather than
    // a link to itself.
    expect(breadcrumbLabels($html))->toBe(['All files', 'Photos', '2026'])
        ->and(breadcrumbLinks($html))->toHaveCount(2)
        ->and(breadcrumbLinks($html)[0])->toBe(MediaLibrary::getUrl())
        ->and(breadcrumbLinks($html)[1])->toContain('folder='.$parent->getKey());
});

it('stops the trail where a parent chain never reaches the library', function (): void {
    $outer = makeFolder('Alpha');
    $inner = makeFolder('Mid', ['parent_id' => $outer->getKey()]);

    MediaFolder::query()->whereKey($outer->getKey())->update(['parent_id' => $inner->getKey()]);

    // A cycle has no top to walk back to, so the trail is the chain that leads
    // into it rather than a walk that never ends.
    expect(breadcrumbLabels(mediaPage()->set('folder', $inner->getKey())->html()))
        ->toBe(['All files', 'Alpha', 'Mid']);
});

it('states how to file a file by dragging, rather than showing a bare icon', function (): void {
    $html = mediaPage()->html();

    // The callout only renders a text wrapper for a heading, a description or a
    // footer, so text passed as a child of the component is dropped and the hint
    // is left as an icon that says nothing.
    expect($html)->toContain('fi-callout bkm-library__hint')
        ->and($html)->toContain('Drag a file onto a folder to file it there.')
        ->and($html)->toContain('fi-callout-description');
});

it('is only empty when there is nothing at all to show', function (): void {
    mediaPage()
        ->assertSee('No media yet');

    $parent = makeFolder('Photos');

    mediaPage()
        ->set('folder', $parent->getKey())
        ->assertSee('This folder is empty');

    // A folder that holds a subfolder is not empty, even though it holds no
    // files, so the empty state must not stand in for the folder to open.
    makeFolder('2026', ['parent_id' => $parent->getKey()]);

    mediaPage()
        ->set('folder', $parent->getKey())
        ->assertDontSee('This folder is empty')
        ->assertSeeHtml('data-folder="2026"');
});

it('draws the folder tree in name order with subfolders nested under their parent', function (): void {
    makeFolder('Zulu');
    $parent = makeFolder('Alpha');
    makeFolder('Mid', ['parent_id' => $parent->getKey()]);

    mediaPage()
        ->assertSeeHtmlInOrder([
            'data-folder="alpha"',
            'data-folder="mid"',
            'data-folder="zulu"',
        ])
        ->assertSeeHtml('data-depth="0"')
        ->assertSeeHtml('data-depth="1"');
});

it('still draws a folder whose parent chain never reaches the top', function (): void {
    $outer = makeFolder('Alpha');
    $inner = makeFolder('Mid', ['parent_id' => $outer->getKey()]);

    // A cycle needs a parent chain written straight to the table, because the
    // page only ever creates a folder inside the one that is open.
    MediaFolder::query()->whereKey($outer->getKey())->update(['parent_id' => $inner->getKey()]);

    $html = mediaPage()
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'data-folder="alpha"'))->toBe(1)
        ->and(substr_count($html, 'data-folder="mid"'))->toBe(1)
        ->and(strpos($html, 'data-folder="alpha"'))->toBeLessThan(strpos($html, 'data-folder="mid"'));
});

it('keeps the stored url as the form value of the picker', function (): void {
    $media = makeMedia();

    expect(MediaPicker::resolveStoredValue($media->getKey()))->toBe($media->storageUrl());
});

it('resolves a stored url back to the media id when the form is hydrated', function (): void {
    $media = makeMedia();

    expect(MediaPicker::resolveKey($media->storageUrl()))->toBe($media->getKey())
        ->and(MediaPicker::resolveKey($media->path))->toBe($media->getKey());
});

it('passes a legacy url straight through the picker', function (): void {
    expect(MediaPicker::resolveKey('/storage/media/legacy.jpg'))->toBe('/storage/media/legacy.jpg')
        ->and(MediaPicker::resolveStoredValue('/storage/media/legacy.jpg'))->toBe('/storage/media/legacy.jpg');
});

it('drops a blank picker value on save', function (): void {
    expect(MediaPicker::resolveStoredValue(''))->toBeNull()
        ->and(MediaPicker::resolveKey(null))->toBeNull();
});
