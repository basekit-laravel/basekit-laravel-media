<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament;

use BasekitLaravel\BasekitLaravelMedia\Filament\Pages\MediaLibrary;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Registers the media admin on a panel:
 *
 *     $panel->plugin(new MediaPlugin)
 */
final class MediaPlugin implements Plugin
{
    public function getId(): string
    {
        return 'basekit-laravel-media';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            MediaLibrary::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
