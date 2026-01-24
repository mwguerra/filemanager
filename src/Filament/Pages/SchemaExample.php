<?php

namespace MWGuerra\FileManager\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\FileManager\FileManagerPlugin;
use MWGuerra\FileManager\Schemas\Components\FileManagerEmbed;
use MWGuerra\FileManager\Schemas\Components\FileSystemEmbed;

class SchemaExample extends Page implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    protected string $view = 'filemanager::filament.pages.schema-example';

    protected static string $routePath = 'schema-example';

    public ?array $data = [];

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-squares-plus';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Schema Example';
    }

    public static function getNavigationLabel(): string
    {
        return 'Schema Example';
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

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'schema-example';
    }

    public function mount(): void
    {
        $this->form->fill([
            'title' => 'My Example Title',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->description('Enter the basic details for this example.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->default('My Example Title')
                            ->maxLength(255),
                    ]),

                Section::make('File Manager (Database Mode)')
                    ->description('Browse and manage files stored in the database.')
                    ->collapsible()
                    ->schema([
                        FileManagerEmbed::make()
                            ->height('400px')
                            ->showHeader(true)
                            ->showSidebar(true)
                            ->defaultViewMode('grid'),
                    ]),

                Section::make('File System (Storage Mode)')
                    ->description('Browse files directly from the filesystem.')
                    ->collapsible()
                    ->schema([
                        FileSystemEmbed::make()
                            ->height('400px')
                            ->showHeader(true)
                            ->showSidebar(true)
                            ->defaultViewMode('grid'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Submit')
            ->icon('heroicon-o-check')
            ->action(function () {
                $this->dispatch('open-modal', id: 'form-data-modal');
            });
    }

    public function getFormData(): array
    {
        return $this->form->getState();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->submitAction(),
        ];
    }
}
