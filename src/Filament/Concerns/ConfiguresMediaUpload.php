<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament\Concerns;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use Filament\Forms\Components\FileUpload;

/**
 * The upload field the media admin and the picker both offer, so the two cannot
 * drift apart on where a file lands or how large it may be.
 */
trait ConfiguresMediaUpload
{
    protected function mediaUpload(string $name = 'path', ?string $directory = null): FileUpload
    {
        $upload = FileUpload::make($name)
            ->label(__('basekit-laravel-media::basekit-laravel-media.file'))
            ->disk(Media::diskName())
            ->directory($directory ?? Media::uploadDirectory())
            ->image()
            ->acceptedFileTypes(Media::acceptedFileTypes())
            ->storeFileNamesIn('name')
            ->required();

        $maxSize = Media::maxUploadSize();

        // Filament's own ceiling stays in force when the package is not given one:
        // setting the field with nothing would replace it with an empty limit.
        return $maxSize === null ? $upload : $upload->maxSize($maxSize);
    }
}
