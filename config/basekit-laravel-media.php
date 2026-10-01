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

];
