<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The media library stores uploaded files on a Laravel filesystem disk,
    | in a subdirectory of that disk. Choose a public disk so the stored
    | files get a web URL the rendered pages can use. The featured image of
    | a post is the URL of a media item, so keep this disk alive for as long
    | as posts refer to files on it.
    |
    */

    'disk' => (string) env('BASEKIT_MEDIA_DISK', 'public'),

    'path' => (string) env('BASEKIT_MEDIA_PATH', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Soft deletes
    |--------------------------------------------------------------------------
    |
    | Whether deleting a file leaves the row behind, or takes it away for good.
    |
    | True, the default: a deleted file leaves the library but stays on the disk
    | and can be put back with `restore()`. False: a deleted file is gone, row and
    | blob both, which is what an application that must not keep user-supplied
    | files after a request to remove them wants.
    |
    | Set it in this file rather than through the environment: it decides what
    | deleting means for data already in the table, so it should not shift under
    | an application that has rows written one way or the other.
    |
    | The `deleted_at` column is created either way, so turning this off does not
    | need a migration, and turning it back on finds the rows it needs. Folders
    | are deleted for good in both modes, because a soft deleted folder would go
    | on holding files that then had nowhere to be seen from.
    |
    */

    'soft_deletes' => true,

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | The size ceiling for an uploaded file, in kilobytes, and the file types
    | the uploader accepts. A null size falls back to Filament's own default,
    | which is 12 MB. Filament checks what the browser sends and what actually
    | arrives, so a file outside the accepted types is refused rather than
    | written to the disk.
    |
    */

    'uploads' => [
        'max_size' => env('BASEKIT_MEDIA_MAX_SIZE'),

        'accepted_file_types' => ['image/*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | The gate names the admin screen asks before it shows or changes anything.
    | A null name is the permissive default: anyone who can reach the panel may
    | use the screen, which is what installing the package should not change.
    | Set a name and the screen asks the gate about that instead, so an
    | application can hand editors the picker without handing them the library:
    |
    |     Gate::define('manage-media', fn (User $user): bool => $user->isEditor());
    |
    |     'permissions' => ['view' => 'manage-media', ...]
    |
    | A policy the application registers for Media or MediaFolder is consulted
    | before any of this, so an application that already gates those models with
    | policies is not also asked to define these names.
    |
    */

    'permissions' => [
        'view' => env('BASEKIT_MEDIA_VIEW_PERMISSION'),
        'create' => env('BASEKIT_MEDIA_CREATE_PERMISSION'),
        'update' => env('BASEKIT_MEDIA_UPDATE_PERMISSION'),
        'delete' => env('BASEKIT_MEDIA_DELETE_PERMISSION'),
    ],

];
