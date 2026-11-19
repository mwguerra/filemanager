<?php

namespace MWGuerra\FileManager\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use MWGuerra\FileManager\FileManagerServiceProvider;
use MWGuerra\FileManager\Models\FileSystemItem;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start session for Livewire components
        $this->app['session']->start();

        // Share empty error bag with views for Livewire components
        $this->app['view']->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            SupportServiceProvider::class,
            FileManagerServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use a testing disk for storage tests
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing'),
        ]);

        // Session configuration
        $app['config']->set('session.driver', 'array');

        // Rendering a component's view encrypts its CSRF token and snapshot
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        // FileManager configuration
        $app['config']->set('filemanager.mode', 'storage');
        $app['config']->set('filemanager.model', FileSystemItem::class);
        $app['config']->set('filemanager.storage_mode.disk', 'testing');
        $app['config']->set('filemanager.storage_mode.root', '');
        $app['config']->set('filemanager.storage_mode.show_hidden', false);
        $app['config']->set('filemanager.upload.disk', 'testing');
        $app['config']->set('filemanager.upload.directory', 'uploads');
    }
}
