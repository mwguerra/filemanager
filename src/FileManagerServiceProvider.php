<?php

namespace MWGuerra\FileManager;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use MWGuerra\FileManager\FileTypes\ArchiveFileType;
use MWGuerra\FileManager\FileTypes\AudioFileType;
use MWGuerra\FileManager\FileTypes\DocumentFileType;
use MWGuerra\FileManager\FileTypes\ImageFileType;
use MWGuerra\FileManager\FileTypes\OtherFileType;
use MWGuerra\FileManager\FileTypes\PdfFileType;
use MWGuerra\FileManager\FileTypes\TextFileType;
use MWGuerra\FileManager\FileTypes\VideoFileType;
use MWGuerra\FileManager\Livewire\EmbeddedFileManager;
use MWGuerra\FileManager\Livewire\EmbeddedFileSystem;
use MWGuerra\FileManager\Livewire\FileManagerSidebar;
use MWGuerra\FileManager\Policies\FileSystemItemPolicy;
use MWGuerra\FileManager\Console\Commands\FileSystemListCommand;
use MWGuerra\FileManager\Console\Commands\InstallCommand;
use MWGuerra\FileManager\Console\Commands\ListFilesCommand;
use MWGuerra\FileManager\Console\Commands\RebuildFileSystemItemsCommand;
use MWGuerra\FileManager\Console\Commands\UploadFolderCommand;
use MWGuerra\FileManager\Services\AuthorizationService;
use MWGuerra\FileManager\Services\FileSecurityService;
use MWGuerra\FileManager\Services\FileUrlService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FileManagerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filemanager';

    /**
     * Built-in file type classes mapped to their config keys.
     */
    protected array $builtInFileTypes = [
        'video' => VideoFileType::class,
        'image' => ImageFileType::class,
        'audio' => AudioFileType::class,
        'pdf' => PdfFileType::class,
        'text' => TextFileType::class,
        'document' => DocumentFileType::class,
        'archive' => ArchiveFileType::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_file_system_items_table',
            ])
            ->hasCommands([
                FileSystemListCommand::class,
                InstallCommand::class,
                ListFilesCommand::class,
                RebuildFileSystemItemsCommand::class,
                UploadFolderCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register FileTypeRegistry as a singleton
        $this->app->singleton(FileTypeRegistry::class, function () {
            return new FileTypeRegistry();
        });

        // Register FileSecurityService as a singleton
        $this->app->singleton(FileSecurityService::class, function () {
            return new FileSecurityService();
        });

        // Register AuthorizationService as a singleton
        $this->app->singleton(AuthorizationService::class, function () {
            return new AuthorizationService();
        });

        // Register FileUrlService as a singleton
        $this->app->singleton(FileUrlService::class, function () {
            return new FileUrlService();
        });

        // Register the policy class
        $this->app->singleton(FileSystemItemPolicy::class, function () {
            $policyClass = config('filemanager.authorization.policy', FileSystemItemPolicy::class);
            return new $policyClass();
        });
    }

    public function packageBooted(): void
    {
        $this->registerAssets();
        $this->registerFileTypes();
        $this->registerViewComponents();
        $this->registerLivewireComponents();
        $this->registerPublishables();
        $this->registerRoutes();
    }

    /**
     * Register package routes for file streaming.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register plugin assets with Filament.
     */
    protected function registerAssets(): void
    {
        FilamentAsset::register([
            Css::make('filemanager', __DIR__ . '/../resources/dist/filemanager.css'),
        ], 'mwguerra/filemanager');
    }

    /**
     * Register built-in and custom file types.
     */
    protected function registerFileTypes(): void
    {
        $registry = $this->app->make(FileTypeRegistry::class);

        // Register built-in types (if enabled in config)
        foreach ($this->builtInFileTypes as $key => $class) {
            if (config("filemanager.file_types.{$key}", true)) {
                $registry->register(new $class());
            }
        }

        // Register custom types from config
        $customTypes = config('filemanager.file_types.custom', []);
        foreach ($customTypes as $class) {
            if (class_exists($class)) {
                $registry->register($this->app->make($class));
            }
        }

        // Set the fallback type
        $registry->setFallback(new OtherFileType());
    }

    /**
     * Register blade view components.
     */
    protected function registerViewComponents(): void
    {
        // Register viewer components with the filemanager namespace
        Blade::componentNamespace('MWGuerra\\FileManager\\View\\Components', 'filemanager');
    }

    /**
     * Register Livewire components for embedding.
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('embedded-file-manager', EmbeddedFileManager::class);
        Livewire::component('embedded-file-system', EmbeddedFileSystem::class);
        Livewire::component('filemanager-sidebar', FileManagerSidebar::class);
    }

    /**
     * Register publishable assets.
     */
    protected function registerPublishables(): void
    {
        // Publish Livewire config optimized for large file uploads
        $this->publishes([
            __DIR__ . '/../stubs/livewire.php.stub' => config_path('livewire.php'),
        ], 'filemanager-livewire-config');

        // Publish PHP ini settings for upload limits
        $this->publishes([
            __DIR__ . '/../stubs/.user.ini.stub' => base_path('.user.ini'),
        ], 'filemanager-php-config');

        // Publish all config files at once
        $this->publishes([
            __DIR__ . '/../stubs/livewire.php.stub' => config_path('livewire.php'),
            __DIR__ . '/../stubs/.user.ini.stub' => base_path('.user.ini'),
        ], 'filemanager-upload-config');

        // Publish viewer components for customization
        $this->publishes([
            __DIR__ . '/../resources/views/components/viewers' => resource_path('views/vendor/filemanager/components/viewers'),
        ], 'filemanager-viewers');

        // Publish model stub for customization
        $this->publishes([
            __DIR__ . '/../stubs/FileSystemItem.php.stub' => app_path('Models/FileSystemItem.php'),
        ], 'filemanager-model');

        // Publish filesystem disk configurations (for reference)
        $this->publishes([
            __DIR__ . '/../stubs/filesystems.php.stub' => base_path('stubs/filemanager/filesystems.php'),
        ], 'filemanager-filesystems');

        // Publish environment variables example
        $this->publishes([
            __DIR__ . '/../stubs/.env.filemanager.stub' => base_path('.env.filemanager.example'),
        ], 'filemanager-env');

        // Publish all stubs at once for quick setup
        $this->publishes([
            __DIR__ . '/../stubs/livewire.php.stub' => config_path('livewire.php'),
            __DIR__ . '/../stubs/.user.ini.stub' => base_path('.user.ini'),
            __DIR__ . '/../stubs/filesystems.php.stub' => base_path('stubs/filemanager/filesystems.php'),
            __DIR__ . '/../stubs/.env.filemanager.stub' => base_path('.env.filemanager.example'),
        ], 'filemanager-stubs');
    }
}
