# Basekit Laravel Media

Media library for Basekit Laravel. Uploaded files live on a Laravel filesystem
disk; the package keeps the storage metadata, lets editors organise files into
a folder hierarchy through a Filament panel, and provides a drop-in picker
field any other package can embed to let editors choose an existing file.

| Component | Constraint |
| --- | --- |
| PHP | `^8.4` or `^8.5` |
| Laravel | `^13` |
| Filament | `^5` (optional) |

## Components

- **`Media` model** — `BasekitLaravel\BasekitLaravelMedia\Models\Media`; one row
  per stored file.
- **`MediaFolder` model** — `BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder`;
  a nestable folder a file can belong to.
- **`MediaWriter`** — `BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter`;
  fills storage metadata and keeps the disk file in sync with the row.
- **`MediaPlugin`** — `BasekitLaravel\BasekitLaravelMedia\Filament\MediaPlugin`;
  registers the media library screen on a panel.
- **`MediaPicker`** — `BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker`;
  a form field backed by a selection table in a modal.

## Installation

```bash
composer require basekit-laravel/basekit-laravel-media
```

Plus, to use the admin screens and the picker:

```bash
composer require filament/filament
```

```bash
php artisan vendor:publish --tag="basekit-laravel-media-migrations"
php artisan migrate
```

The migration runs automatically; publishing is only needed if you want to own
the file.

To configure the storage disk and path:

```bash
php artisan vendor:publish --tag="basekit-laravel-media-config"
```

```php
return [
    'disk' => env('BASEKIT_MEDIA_DISK', 'public'),     // filesystem disk
    'path' => env('BASEKIT_MEDIA_PATH', 'media'),      // directory on the disk
];
```

## Registering the admin screens

```php
use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(new MediaPlugin);
}
```

The plugin adds a single **Media** screen that holds the folders and the files
together, so an editor never has to work between two lists:

- a folder tree down the side, with the files of the open folder to the right and
  the whole library when *All files* is open;
- the folders inside the open folder as tiles above the file tiles, so the
  contents of a folder can be walked the way a file browser walks them, with a
  breadcrumb trail above the search to walk back out again;
- **Upload** and **New folder** in the page header, both working on whichever
  folder is open, so a new folder can be created inside the current one;
- dragging a file tile onto a folder to file it there, or onto *All files* to
  take it back out of its folder;
- a **Move** action on every tile for the same job without dragging, and
  **Edit** and **Delete** beside it. The buttons are dimmed until the tile is
  hovered rather than hidden until then, and clicking the image opens the **Edit**
  form with the file shown above the name and alt text, so both are written
  against the image they describe.

Folders stay nestable, and the open folder and the search term live in the URL,
so a folder view can be linked to and survives a refresh.

The file a storage area is uploaded into follows the open folder: choosing a
folder at upload time stores the file under `path/folder-slug`. Moving a file
between folders later only changes the metadata, never the disk file, so
published URLs stay stable. Deleting a folder keeps the files it held — they
fall back to *All files*.

## Choosing a file from another form

`MediaPicker` is a drop-in replacement for a URL field. The form works on the
media item's ID while the editor picks; the stored value is the file's web URL,
exactly what a page already renders, so consuming models keep the string they
publish:

```php
use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker;

MediaPicker::make('featured_image')->columnSpan(2)
```

Values that are URLs rather than IDs are shown as-is and saved back unchanged,
so existing data placed there before the picker is never lost.

The picker carries an *Upload new* action, so an editor can add a file without
leaving the form: the file lands in the media library, and the picker selects
it as soon as it is stored. The action is a labeled button next to *Select*
rather than an affix, because the `ModalTableSelect` view the picker extends
renders its select action and never its affix actions — and it is repeated in
the footer of the media selection modal, where the table occupies the whole
screen and the field itself is not visible.

## Beyond the picker

```php
use BasekitLaravel\BasekitLaravelMedia\Models\Media;

Media::query()->orderBy('id', 'desc')->paginate(24); // index pages
$media->storageUrl();                                 // web URL for an <img>
Media::findByStorageValue('/storage/media/cover.jpg'); // resolve a stored value
```

## Development

```bash
composer install
composer test      # Pest suite (Orchestra Testbench)
composer analyse   # PHPStan / Larastan
composer format    # Laravel Pint
composer lint      # Pint --test
```

## License

MIT