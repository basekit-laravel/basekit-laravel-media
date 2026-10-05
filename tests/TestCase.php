<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Tests;

use BasekitLaravel\BasekitLaravelMedia\BasekitLaravelMediaServiceProvider;
use BasekitLaravel\BasekitLaravelMedia\Tests\TestSupport\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * The disk root this test wrote to, so it can be taken away again afterwards.
     */
    private ?string $mediaDiskRoot = null;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->migratePackageDatabase();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->mediaDiskRoot !== null) {
            (new Filesystem)->deleteDirectory($this->mediaDiskRoot);

            $this->mediaDiskRoot = null;
        }

        parent::tearDown();
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

        // The media library writes to the configured disk, so the suite points it
        // at a local disk. Each test gets its own root and the root is deleted
        // when the test ends: the suite uploads real files, and a shared root
        // would leave every run's uploads behind for the next one to trip over.
        // The `url` key makes `Media::storageUrl()` deterministic.
        $this->mediaDiskRoot = storage_path('app/testing/'.Str::random(12));

        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => $this->mediaDiskRoot,
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
