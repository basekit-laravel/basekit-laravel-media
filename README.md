# Basekit Laravel Media

[![CI](https://github.com/basekit-laravel/basekit-laravel-media/actions/workflows/ci.yml/badge.svg)](https://github.com/basekit-laravel/basekit-laravel-media/actions/workflows/ci.yml) [![Packagist](https://img.shields.io/packagist/v/basekit-laravel/basekit-laravel-media.svg)](https://packagist.org/packages/basekit-laravel/basekit-laravel-media) [![License](https://img.shields.io/packagist/l/basekit-laravel/basekit-laravel-media.svg)](LICENSE.md) [![PHP](https://img.shields.io/packagist/php-v/basekit-laravel/basekit-laravel-media.svg)](https://packagist.org/packages/basekit-laravel/basekit-laravel-media) [![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20.svg)](https://laravel.com) [![Filament](https://img.shields.io/badge/Filament-5-fbbf00.svg)](https://filamentphp.com)

Media library for Laravel: files on a Laravel filesystem disk, their metadata in the database, a folder hierarchy for editors, and a Filament admin screen.

Moving a file between folders changes metadata only, so published URLs never break.

## 📋 Contents

- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [The admin screen](#-the-admin-screen)
- [Permissions](#-permissions)
- [Uploads](#-uploads)
- [Deleting and restoring](#-deleting-and-restoring)
- [Using media from your own code](#-using-media-from-your-own-code)
- [Custom views and translations](#-custom-views-and-translations)
- [Security](#-security)
- [Limitations](#-limitations)
- [Development](#-development)

## 🧰 Requirements

- PHP 8.4 or 8.5
- Laravel 13
- Filament 5 (optional — only the admin screen needs it)

## ⬇️ Installation

```bash
composer require basekit-laravel/basekit-laravel-media
```

Package discovery registers the service provider and runs the migration. Publish it only if you want to own the file:

```bash
php artisan vendor:publish --tag="basekit-laravel-media-migrations"
php artisan migrate
```

The admin screen needs Filament:

```bash
composer require filament/filament
```

The screen's stylesheet ships as a Filament asset, so publishing the assets is what puts it on the panel. Run this again after every upgrade:

```bash
php artisan filament:assets
```

## ⚙️ Configuration

```bash
php artisan vendor:publish --tag="basekit-laravel-media-config"
```

Everything lives in `config/basekit-laravel-media.php`:

```php
return [
    // Where uploads are stored: a Laravel disk and a directory on it.
    'disk' => env('BASEKIT_MEDIA_DISK', 'public'),
    'path' => env('BASEKIT_MEDIA_PATH', 'media'),

    // true: delete() keeps the row and restore() brings it back.
    // false: delete() removes the row and the file.
    'soft_deletes' => true,

    'uploads' => [
        'max_size' => env('BASEKIT_MEDIA_MAX_SIZE'),   // kilobytes; null for Filament's 12 MB
        'accepted_file_types' => ['image/*'],
    ],

    // Gate names. null allows the ability without asking.
    'permissions' => [
        'view' => env('BASEKIT_MEDIA_VIEW_PERMISSION'),
        'create' => env('BASEKIT_MEDIA_CREATE_PERMISSION'),
        'update' => env('BASEKIT_MEDIA_UPDATE_PERMISSION'),
        'delete' => env('BASEKIT_MEDIA_DELETE_PERMISSION'),
    ],
];
```

Use a **public** disk so stored files get a web URL. A page stores that URL, so keep the disk alive for as long as pages refer to files on it.

`soft_deletes` belongs in this file rather than in `.env`: it decides what deleting means for rows already in the table, so it should not flip with a deploy.

## 🖥️ The admin screen

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

The plugin adds a single **Media** screen that holds the folders and the files together.

<!-- ![The media admin screen](docs/media-screen.png) -->

## 🔒 Permissions

By default anyone who can reach the panel can use the media screen — installing the package does not lock an existing panel out of its own library.

To restrict an ability, define a gate and point the ability at it:

```php
Gate::define('manage-media', fn (User $user): bool => $user->isEditor());
```

```php
'permissions' => [
    'view' => 'manage-media',
],
```

The same thing through `.env` is `BASEKIT_MEDIA_VIEW_PERMISSION=manage-media`. A `null` name — the default — allows the ability without asking.

| Ability  | Covers                                                 |
| -------- | ------------------------------------------------------ |
| `view`   | Opening the **Media** screen.                          |
| `create` | **Upload** and **New folder**.                         |
| `update` | **Edit**, **Move**, and dragging a file onto a folder. |
| `delete` | **Delete** on a file or a folder.                      |

If your application already has a policy on `Media` or `MediaFolder`, that policy is asked first and is about the individual row — you do not need to define these gate names as well.

## ⬆️ Uploads

Set `uploads.max_size` in kilobytes (`null` falls back to Filament's 12 MB) and `uploads.accepted_file_types`. Filament checks both what the browser sends and what actually arrives, so a file outside the limits is refused rather than written.

```php
Media::maxUploadSize();      // 5120, or null
Media::acceptedFileTypes();  // ['image/*']
```

A file is stored at `path/folder-slug`, under the folder that is open when it is uploaded.

## 🗑️ Deleting and restoring

```php
$media->delete();        // gone from the library, still on the disk
$media->restore();       // back in the library, still on the disk
$media->forceDelete();   // row and file gone for good
```

### Turning soft deletes off

An application that must not keep user-supplied files after a removal can have every deletion take the row and the file together:

```php
'soft_deletes' => false,
```

With it off, `delete()` is a hard delete and there is nothing for `restore()` to bring back; the confirmation an editor reads says so.

- The `deleted_at` column is created either way, so changing the setting needs no migration.
- Folders are always deleted for good, in both modes. Their files fall back to _All files_.

## 💻 Using media from your own code

```php
use BasekitLaravel\BasekitLaravelMedia\Models\Media;

Media::query()->orderBy('id', 'desc')->paginate(24);       // index pages
$media->storageUrl();                                       // web URL for an <img>
$media->humanSize();                                        // "1.2 MB" for a listing
Media::findByStorageValue('/storage/media/cover.jpg');     // resolve a stored value
```

`findByStorageValue()` matches a stored web URL or path back to its row.

## 🎨 Custom views and translations

```bash
php artisan vendor:publish --tag="basekit-laravel-media-views"
php artisan vendor:publish --tag="basekit-laravel-media-translations"
```

They land in `resources/views/vendor/basekit-laravel-media` and `lang/vendor/basekit-laravel-media`. The views render standalone and never assume your application's layout.

The stylesheets are not part of that: they live in `resources/css/basekit-laravel-media/` and are registered as Filament assets, so a published view carries no CSS of its own.

Class names follow BEM with `bkm` as the package prefix: `bkm-tile`, `bkm-tile__preview`, `bkm-folder--over`. `bkm` on its own is the prefix, never a block.

## 🚧 Limitations

The package does not convert, resize or process files, and it does not read image dimensions.

It does not provide:

- image, video or PDF previews beyond the stored URL
- tagging, favouriting or search beyond the file name
- per-application tenancy of the library

## 🧪 Development

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

## 📄 License

Basekit Laravel Media is open-source software licensed under the [**MIT License**](LICENSE.md).
