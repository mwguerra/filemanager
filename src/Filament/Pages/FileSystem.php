<?php

namespace MWGuerra\FileManager\Filament\Pages;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\FileManager\Adapters\AdapterFactory;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\FileManagerPlugin;

/**
 * File System page - shows files directly from storage disk (read-only).
 *
 * This page always uses storage mode, reading files directly from
 * the configured Laravel Storage disk (local, S3, etc.).
 * This is a read-only view - only browsing and downloading is allowed.
 * For write operations, use the FileManager page instead.
 */
class FileSystem extends FileManager
{
    protected static string $routePath = 'file-system';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return FileManagerPlugin::current()?->getFileSystemNavigationIcon()
            ?? config('filemanager.file_system.navigation.icon', 'heroicon-o-server-stack');
    }

    public function getTitle(): string|Htmlable
    {
        return FileManagerPlugin::current()?->getFileSystemNavigationLabel()
            ?? config('filemanager.file_system.navigation.label', 'File System');
    }

    public static function getNavigationLabel(): string
    {
        return FileManagerPlugin::current()?->getFileSystemNavigationLabel()
            ?? config('filemanager.file_system.navigation.label', 'File System');
    }

    public static function getNavigationSort(): ?int
    {
        return FileManagerPlugin::current()?->getFileSystemNavigationSort()
            ?? config('filemanager.file_system.navigation.sort', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return FileManagerPlugin::get()->getFileSystemNavigationGroup();
        } catch (\Throwable) {
            return config('filemanager.file_system.navigation.group')
                ?? config('filemanager.file_manager.navigation.group', 'FileManager');
        }
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'file-system';
    }

    /**
     * Check if the page sidebar should be shown.
     */
    public function shouldShowPageSidebar(): bool
    {
        return FileManagerPlugin::current()?->isFileSystemPageSidebarEnabled() ?? true;
    }

    /**
     * Get the sidebar root label.
     */
    public function getSidebarRootLabel(): string
    {
        return FileManagerPlugin::current()?->getFileSystemSidebarRootLabel() ?? 'Root';
    }

    /**
     * Get the sidebar heading.
     */
    public function getSidebarHeading(): string
    {
        return FileManagerPlugin::current()?->getFileSystemSidebarHeading() ?? 'Folders';
    }

    /**
     * Always use storage adapter for this page.
     */
    protected function getAdapter(): FileManagerAdapterInterface
    {
        return AdapterFactory::makeStorage();
    }

    /**
     * This page is always in storage mode.
     */
    public function getMode(): string
    {
        return 'storage';
    }

    /**
     * Always true for this page.
     */
    public function isStorageMode(): bool
    {
        return true;
    }

    /**
     * Always false for this page.
     */
    public function isDatabaseMode(): bool
    {
        return false;
    }

    /**
     * Get the storage disk being used.
     */
    public function getStorageDiskProperty(): ?string
    {
        return config('filemanager.storage_mode.disk', 'public');
    }

    /**
     * File System is always read-only.
     * Only browsing and downloading is allowed.
     */
    public function isReadOnly(): bool
    {
        return true;
    }
}
