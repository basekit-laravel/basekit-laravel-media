<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

final class BasekitLaravelMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/basekit-laravel-media.php',
            'basekit-laravel-media',
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'basekit-laravel-media');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'basekit-laravel-media');

        $this->registerFilamentAssets();

        $this->publishes([
            __DIR__.'/../config/basekit-laravel-media.php' => config_path('basekit-laravel-media.php'),
        ], 'basekit-laravel-media-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'basekit-laravel-media-migrations');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/basekit-laravel-media'),
        ], 'basekit-laravel-media-translations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/basekit-laravel-media'),
        ], 'basekit-laravel-media-views');
    }

    /**
     * Registered here rather than in MediaPlugin so the stylesheet is available to
     * any panel, and guarded because Filament is a suggested dependency.
     */
    private function registerFilamentAssets(): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        FilamentAsset::register([
            Css::make(
                'basekit-laravel-media/media-library',
                __DIR__.'/../resources/css/dist/media-library.css',
            ),
            Css::make(
                'basekit-laravel-media/edit-preview',
                __DIR__.'/../resources/css/dist/edit-preview.css',
            ),
        ]);
    }
}
