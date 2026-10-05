<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament;

use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The table the picker embeds, so it lists every media item with a search and lets
 * an editor choose one row.
 */
final class MediaTableConfiguration
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Media::query()->with('folder'))
            ->searchable()
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.preview'))
                    ->getStateUsing(fn (Media $record): string => $record->storageUrl())
                    ->square(),
                TextColumn::make('name')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.name'))
                    ->searchable(),
                TextColumn::make('folder.name')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.folder'))
                    ->placeholder('—'),
            ]);
    }
}
