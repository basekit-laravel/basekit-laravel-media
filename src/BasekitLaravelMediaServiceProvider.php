<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia;

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

        $this->publishes([
            __DIR__.'/../config/basekit-laravel-media.php' => config_path('basekit-laravel-media.php'),
        ], 'basekit-laravel-media-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'basekit-laravel-media-migrations');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/basekit-laravel-media'),
        ], 'basekit-laravel-media-translations');
    }
}
