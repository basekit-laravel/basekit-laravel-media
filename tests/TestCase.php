<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Tests;

use BasekitLaravel\BasekitLaravelMedia\BasekitLaravelMediaServiceProvider;
use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->migratePackageDatabase();
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            BasekitLaravelMediaServiceProvider::class,
        ];
    }

    #[\Override]
    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // The media library writes to the configured disk, so the suite points
        // it at a local disk rooted in the ephemeral storage directory. The
        // `url` key makes `Media::storageUrl()` deterministic.
        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('app/testing'),
            'url' => '/storage',
            'visibility' => 'public',
        ]);
        $app['config']->set('basekit-laravel-media.disk', 'testing');

        // Filament's auth middleware asks the *guard's* user model whether it
        // may enter the panel, and refuses anyone whose model does not
        // implement `FilamentUser` outside the `local` environment.
        $app['config']->set('auth.providers.users.model', User::class);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * The package publishes its migrations to the consuming application, so
     * the suite applies them itself from the installed package.
     */
    protected function migratePackageDatabase(): void
    {
        $files = glob(__DIR__.'/../database/migrations/*.php');

        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $migration = include $file;

            $migration->up();
        }
    }
}
