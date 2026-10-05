# Basekit Laravel Media

[![CI](https://github.com/basekit-laravel/basekit-laravel-media/actions/workflows/ci.yml/badge.svg)](https://github.com/basekit-laravel/basekit-laravel-media/actions/workflows/ci.yml) [![Packagist](https://img.shields.io/packagist/v/basekit-laravel/basekit-laravel-media.svg)](https://packagist.org/packages/basekit-laravel/basekit-laravel-media) [![License](https://img.shields.io/packagist/l/basekit-laravel/basekit-laravel-media.svg)](LICENSE.md) [![PHP](https://img.shields.io/packagist/php-v/basekit-laravel/basekit-laravel-media.svg)](https://packagist.org/packages/basekit-laravel/basekit-laravel-media) [![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20.svg)](https://laravel.com) [![Filament](https://img.shields.io/badge/Filament-5-fbbf00.svg)](https://filamentphp.com)

Media library for Laravel. Uploaded files live on a Laravel filesystem disk; the package keeps the storage metadata, lets editors organise files into a folder hierarchy through a Filament panel, and provides a drop-in picker field any other package can embed so editors can choose an existing file.

The database row and the file on the disk are kept apart on purpose. Moving a file between folders only changes metadata, so a published URL never breaks, and whether deleting keeps the file is a setting rather than a given.

## Requirements

- PHP 8.4 or 8.5
- Laravel 13
- Filament 5 (optional — only needed for the admin screen and the picker field)

## Installation

```bash
composer require basekit-laravel/basekit-laravel-media
```

Package discovery registers the service provider and runs the migration automatically. Publishing the migration is only needed if you want to own the file:

```bash
php artisan vendor:publish --tag="basekit-laravel-media-migrations"
php artisan migrate
```

To use the admin screen and the picker field, Filament is required as well:

```bash
composer require filament/filament
```

## The admin screen

Register the plugin on a panel:

```php
use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(new MediaPlugin);
}
```

The plugin adds a single **Media** screen that holds the folders and the files together, so an editor never has to work between two lists:

- a folder tree down the side, with the files of the open folder to the right and the whole library when *All files* is open;
- the folders inside the open folder as tiles above the file tiles, plus a breadcrumb trail to walk back out again;
- **Upload** and **New folder** in the page header, both acting on whichever folder is open;
- dragging a file tile onto a folder to file it there, or onto *All files* to take it back out;
- **Move**, **Edit** and **Delete** on every tile, so every job is also reachable without dragging.

Folders stay nestable, and the open folder and the search term live in the URL, so a folder view can be linked to and survives a refresh. A folder cannot be filed inside itself, however long the chain that would take.

The file an upload lands in follows the open folder: choosing a folder at upload time stores the file under `path/folder-slug`. Moving a file between folders later only changes metadata, never the disk file, so published URLs stay stable. Deleting a folder keeps the files it held — they fall back to *All files*.

## Permissions

Out of the box the media screen asks nothing: anyone who can reach the panel can use it, because installing the package should not lock an existing panel out of its own library. An application that wants to be asked says so in the config:

```php
'permissions' => [
    'view' => env('BASEKIT_MEDIA_VIEW_PERMISSION'),
    'create' => env('BASEKIT_MEDIA_CREATE_PERMISSION'),
    'update' => env('BASEKIT_MEDIA_UPDATE_PERMISSION'),
    'delete' => env('BASEKIT_MEDIA_DELETE_PERMISSION'),
],
```

Each of the four takes a gate name. A name of `null` — the default — allows the ability; a name is asked of the gate instead:

```php
Gate::define('manage-media', fn (User $user): bool => $user->isEditor());
```

```dotenv
BASEKIT_MEDIA_VIEW_PERMISSION=manage-media
```

What each ability covers:

| Ability | Covers |
| --- | --- |
| `view` | Reaching the **Media** screen at all. |
| `create` | The **Upload** and **New folder** actions, and the upload in the picker. |
| `update` | **Edit**, **Move**, and dragging a file into a folder. |
| `delete` | **Delete** on a file or a folder. |

Two details worth knowing:

- A **policy** the application registers for `Media` or `MediaFolder` is consulted before any of these names, and is asked about the individual row. An application that already gates those two models with policies is not also asked to define the names above, and a policy that refuses one row does not have to refuse the whole library.
- The names decide what is *offered*; the actions decide what is *allowed*. A hidden button is a convenience, so every action asks again on the server before it changes anything, including the drag handlers, which the browser can call without touching the page at all.

`MediaPicker` lists the library to anyone who can reach the form it sits on — that is the point of a picker — but it only offers its upload to a user who may create.

## Uploads

The size ceiling and the accepted types live in the config, so an application does not have to subclass anything to change them:

```php
'uploads' => [
    'max_size' => env('BASEKIT_MEDIA_MAX_SIZE'),       // kilobytes; null for Filament's own 12 MB
    'accepted_file_types' => ['image/*'],
],
```

```dotenv
BASEKIT_MEDIA_MAX_SIZE=5120
```

Both are read through the model as well, which is handy when an application needs the same answer outside the uploader:

```php
Media::maxUploadSize();      // 5120, or null
Media::acceptedFileTypes();  // ['image/*']
```

## Deleting and restoring

A file deleted from the library is soft deleted: it leaves the library, but stays on the disk, so a mistake can be taken back and a page that still refers to the URL keeps rendering.

```php
$media->delete();   // gone from the library, still on the disk
$media->restore();  // back in the library, still on the disk
```

Taking the file off the disk for good is a separate, final step:

```php
$media->forceDelete();
```

### Turning soft deletes off

An application that must not keep user-supplied files after a request to remove them — for a retention policy, or for storage cost — can have every deletion take the row and the file together. Set it in the config file:

```php
'soft_deletes' => false,
```

With it off, `delete()` is a hard delete: the row goes and the blob goes with it, and there is nothing for `restore()` to bring back. The confirmation an editor reads says so, rather than promising a restore that cannot happen.

Two things to know about the setting:

- **It is not an environment variable.** It decides what deleting means for rows already in the table, so it should not shift under an application that has written data one way or the other. Edit the published config instead.
- **The `deleted_at` column is created either way**, so turning it off or back on needs no migration. What changes is what `delete()` does with the row.

Folders are always deleted for good, in both modes. A soft deleted folder would go on holding files that had then nowhere to be seen from, so the foreign key nulls and its files fall back to *All files* instead.

## Choosing a file from another form

`MediaPicker` is a drop-in replacement for a URL field. The form works on the media item's ID while the editor picks; the stored value is the file's web URL, exactly what a page already renders, so consuming models keep the string they publish.

```php
use BasekitLaravel\BasekitLaravelMedia\Filament\MediaPicker;

MediaPicker::make('featured_image')->columnSpan(2)
```

Values that are URLs rather than IDs are shown as-is and saved back unchanged, so existing data placed there before the picker is never lost.

The picker carries an *Upload new* action, so an editor can add a file without leaving the form: the file lands in the media library, and the picker selects it as soon as it is stored. The action is a labeled button next to *Select* rather than an affix, because the `ModalTableSelect` view the picker extends renders its select action and never its affix actions — and it is repeated in the footer of the media selection modal, where the table occupies the whole screen and the field itself is not visible.

## Using media from your own code

```php
use BasekitLaravel\BasekitLaravelMedia\Models\Media;

Media::query()->orderBy('id', 'desc')->paginate(24);       // index pages
$media->storageUrl();                                       // web URL for an <img>
Media::findByStorageValue('/storage/media/cover.jpg');     // resolve a stored value
```

`findByStorageValue()` finds the row on the indexed `path` column, so a form full of media fields costs one lookup per field rather than a pass over the library.

## Configuration

The published configuration controls:

- the filesystem disk and the directory uploaded files are stored in
- upload size and accepted file types
- gate names for the four abilities
- whether `delete()` keeps the row (`soft_deletes`)

Publish it with:

```bash
php artisan vendor:publish --tag="basekit-laravel-media-config"
```

```php
return [
    'disk' => env('BASEKIT_MEDIA_DISK', 'public'),     // filesystem disk
    'path' => env('BASEKIT_MEDIA_PATH', 'media'),      // directory on the disk
];
```

`soft_deletes` is the one key that is deliberately *not* an environment variable: it changes what deleting means for rows already in the table, so it should be read from the published file rather than flip with a deploy.

## Custom views and translations

The package's Blade templates are published when a screen needs to be restyled:

```bash
php artisan vendor:publish --tag="basekit-laravel-media-views"
php artisan vendor:publish --tag="basekit-laravel-media-translations"
```

They land in `resources/views/vendor/basekit-laravel-media` and `lang/vendor/basekit-laravel-media`. The views render standalone and never assume the consumer's layout.

## Security

Authorization is enforced on the server, not only in the view. Every action — including the drag handlers, which a browser can call without touching the page — asks `MediaAccess` again before it changes anything, and row-level decisions are asked of a policy about the individual row rather than about the ability in general.

Two media rows may not claim the same `disk` and `path`. The check lives in the model rather than in a unique index, so it raises a validation error the form can show the editor instead of failing as an opaque driver error.

A file is only ever written under the configured disk and path, and the folder a file is filed in is resolved from the media table rather than from the request.

CI runs `composer audit` on every push, so a newly published advisory in the dependency tree fails the build.

## Limitations

The package does not convert, resize or process files, and it does not read image dimensions. Do that in an application-level Filament concern if you need it.

The package does not provide:

- image, video or PDF previews beyond the stored URL
- multi-file pickers
- tagging, favouriting or search across metadata other than the file name
- per-application tenancy of the library

## Development

```bash
composer install

composer test            # Pest suite (Orchestra Testbench)
composer test-coverage   # Pest with an HTML report in build/coverage
composer analyse         # PHPStan / Larastan, over src and tests
composer lint            # Pint --test
composer format          # Pint, apply fixes
composer refactor-dry    # Rector, reporting only
composer check           # lint + analyse + test
composer audit           # Known dependency vulnerabilities
```

## License

MIT.