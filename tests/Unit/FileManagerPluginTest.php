<?php

namespace Tests\Unit;

use MWGuerra\FileManager\FileManagerPlugin;

describe('FileManagerPlugin navigation group', function () {
    beforeEach(function () {
        // Clear any cached plugin instance
        $reflection = new \ReflectionClass(FileManagerPlugin::class);
        $property = $reflection->getProperty('currentInstance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    });

    it('returns default group when not configured', function () {
        $plugin = FileManagerPlugin::make();

        expect($plugin->getFileManagerNavigationGroup())->toBe('FileManager');
        expect($plugin->getFileSystemNavigationGroup())->toBe('FileManager');
    });

    it('returns custom group when configured with a string', function () {
        $plugin = FileManagerPlugin::make()
            ->fileManagerNavigation(group: 'Content')
            ->fileSystemNavigation(group: 'Storage');

        expect($plugin->getFileManagerNavigationGroup())->toBe('Content');
        expect($plugin->getFileSystemNavigationGroup())->toBe('Storage');
    });

    it('returns null when group is explicitly set to null', function () {
        $plugin = FileManagerPlugin::make()
            ->fileManagerNavigation(group: null)
            ->fileSystemNavigation(group: null);

        expect($plugin->getFileManagerNavigationGroup())->toBeNull();
        expect($plugin->getFileSystemNavigationGroup())->toBeNull();
    });

    it('uses default when group parameter is not passed (false)', function () {
        $plugin = FileManagerPlugin::make()
            ->fileManagerNavigation(icon: 'heroicon-o-folder', label: 'Files')
            ->fileSystemNavigation(icon: 'heroicon-o-server');

        // Should use default since group wasn't passed
        expect($plugin->getFileManagerNavigationGroup())->toBe('FileManager');
        expect($plugin->getFileSystemNavigationGroup())->toBe('FileManager');
    });

    it('can set other navigation options while setting group to null', function () {
        $plugin = FileManagerPlugin::make()
            ->fileManagerNavigation(
                icon: 'heroicon-o-folder',
                label: 'My Files',
                sort: 5,
                group: null
            );

        expect($plugin->getFileManagerNavigationIcon())->toBe('heroicon-o-folder');
        expect($plugin->getFileManagerNavigationLabel())->toBe('My Files');
        expect($plugin->getFileManagerNavigationSort())->toBe(5);
        expect($plugin->getFileManagerNavigationGroup())->toBeNull();
    });

    it('respects config value when group not explicitly set', function () {
        config(['filemanager.file_manager.navigation.group' => 'Custom Group']);

        $plugin = FileManagerPlugin::make();

        expect($plugin->getFileManagerNavigationGroup())->toBe('Custom Group');
    });

    it('overrides config when group is explicitly set to null', function () {
        config(['filemanager.file_manager.navigation.group' => 'Custom Group']);

        $plugin = FileManagerPlugin::make()
            ->fileManagerNavigation(group: null);

        expect($plugin->getFileManagerNavigationGroup())->toBeNull();
    });
});
