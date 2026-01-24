<?php

namespace MWGuerra\FileManager\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\FileManager\FileManagerPlugin;
use MWGuerra\FileManager\Schemas\Components\FileManagerEmbed;
use MWGuerra\FileManager\Schemas\Components\FileSystemEmbed;

/**
 * Test page for E2E testing of embed component configurations.
 * This page is only registered in testing environment.
 */
class EmbedConfigTest extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filemanager::filament.pages.embed-config-test';

    protected static string $routePath = 'embed-config-test';

    public ?array $data = [];

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-beaker';
    }

    public static function getNavigationSort(): ?int
    {
        return config('filemanager.file_manager.navigation.sort', 1) + 10;
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return FileManagerPlugin::get()->getFileManagerNavigationGroup();
        } catch (\Throwable) {
            return config('filemanager.file_manager.navigation.group', 'FileManager');
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Embed Configuration Test';
    }

    public static function getNavigationLabel(): string
    {
        return 'Embed Config Test';
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'embed-config-test';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show in navigation during testing
        return app()->environment('testing', 'local');
    }

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Test 1: Custom Sidebar Labels
                Section::make('Test 1: Custom Sidebar Labels')
                    ->description('Testing sidebarRootLabel and sidebarHeading configuration')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-sidebar-labels')
                            ->height('300px')
                            ->sidebarRootLabel('My Files')
                            ->sidebarHeading('Navigation'),
                    ])
                    ->extraAttributes(['data-testid' => 'section-sidebar-labels']),

                // Test 2: Custom Breadcrumbs Label
                Section::make('Test 2: Custom Breadcrumbs Label')
                    ->description('Testing breadcrumbsRootLabel configuration')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-breadcrumbs-label')
                            ->height('300px')
                            ->breadcrumbsRootLabel('Home'),
                    ])
                    ->extraAttributes(['data-testid' => 'section-breadcrumbs-label']),

                // Test 3: Hidden Sidebar
                Section::make('Test 3: Hidden Sidebar')
                    ->description('Testing hideSidebar() configuration')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-hidden-sidebar')
                            ->height('300px')
                            ->hideSidebar(),
                    ])
                    ->extraAttributes(['data-testid' => 'section-hidden-sidebar']),

                // Test 4: Hidden Header
                Section::make('Test 4: Hidden Header')
                    ->description('Testing hideHeader() configuration')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-hidden-header')
                            ->height('300px')
                            ->hideHeader(),
                    ])
                    ->extraAttributes(['data-testid' => 'section-hidden-header']),

                // Test 5: Compact Mode
                Section::make('Test 5: Compact Mode')
                    ->description('Testing compact() configuration (no header, no sidebar)')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-compact')
                            ->height('300px')
                            ->compact(),
                    ])
                    ->extraAttributes(['data-testid' => 'section-compact']),

                // Test 6: List View Mode
                Section::make('Test 6: List View Mode')
                    ->description('Testing defaultViewMode("list") configuration')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-list-view')
                            ->height('300px')
                            ->defaultViewMode('list'),
                    ])
                    ->extraAttributes(['data-testid' => 'section-list-view']),

                // Test 7: FileSystemEmbed with all custom labels
                Section::make('Test 7: FileSystemEmbed Custom Labels')
                    ->description('Testing FileSystemEmbed with all custom labels')
                    ->schema([
                        FileSystemEmbed::make()
                            ->key('test-filesystem-labels')
                            ->height('300px')
                            ->sidebarRootLabel('Storage Root')
                            ->sidebarHeading('Directories')
                            ->breadcrumbsRootLabel('Storage'),
                    ])
                    ->extraAttributes(['data-testid' => 'section-filesystem-labels']),

                // Test 8: Combined sidebar() method
                Section::make('Test 8: Combined Sidebar Method')
                    ->description('Testing sidebar(true, "Custom Root", "Custom Heading") method')
                    ->schema([
                        FileManagerEmbed::make()
                            ->key('test-combined-sidebar')
                            ->height('300px')
                            ->sidebar(true, 'Custom Root', 'Custom Heading'),
                    ])
                    ->extraAttributes(['data-testid' => 'section-combined-sidebar']),
            ])
            ->statePath('data');
    }
}
