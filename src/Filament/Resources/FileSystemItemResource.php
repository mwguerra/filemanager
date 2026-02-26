<?php

namespace MWGuerra\FileManager\Filament\Resources;

use MWGuerra\FileManager\Filament\Resources\FileSystemItemResource\Pages;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use MWGuerra\FileManager\FileManagerPlugin;

class FileSystemItemResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return config('filemanager.model');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return FileManagerPlugin::get()->getFileManagerNavigationGroup();
        } catch (\Throwable) {
            return config('filemanager.file_manager.navigation.group', 'FileManager');
        }
    }

    public static function form(Schema $schema): Schema
    {
        $modelClass = config('filemanager.model');

        return $schema
            ->components([
                Section::make('Item Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->options(collect(FileSystemItemType::cases())->mapWithKeys(
                                fn ($case) => [$case->value => $case->label()]
                            ))
                            ->required()
                            ->live(),

                        Select::make('file_type')
                            ->options(collect(FileType::cases())->mapWithKeys(
                                fn ($case) => [$case->value => $case->label()]
                            ))
                            ->visible(fn (Get $get) => $get('type') === FileSystemItemType::File->value)
                            ->required(fn (Get $get) => $get('type') === FileSystemItemType::File->value),

                        Select::make('parent_id')
                            ->label('Parent Folder')
                            ->relationship('parent', 'name', fn ($query) => $query->where('type', FileSystemItemType::Folder->value))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for root level items'),
                    ])
                    ->columns(2),

                Section::make('File Details')
                    ->schema([
                        TextInput::make('size')
                            ->label('File Size (bytes)')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('duration')
                            ->label('Duration (seconds)')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('thumbnail')
                            ->label('Thumbnail URL')
                            ->url()
                            ->nullable()
                            ->maxLength(255),

                        TextInput::make('storage_path')
                            ->label('Storage Path')
                            ->nullable()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('type') === FileSystemItemType::File->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        $modelClass = config('filemanager.model');

        return $table
            ->columns([
                Tables\Columns\IconColumn::make('type')
                    ->icon(fn (string $state): string => FileSystemItemType::tryFrom($state)?->icon() ?? 'heroicon-o-document')
                    ->color(fn (string $state): string => FileSystemItemType::tryFrom($state)?->color() ?? 'gray'),

                Tables\Columns\IconColumn::make('file_type')
                    ->label('File Type')
                    ->icon(fn (?string $state): string => $state ? (FileType::tryFrom($state)?->icon() ?? 'heroicon-o-document') : '')
                    ->color(fn (?string $state): string => $state ? (FileType::tryFrom($state)?->color() ?? 'gray') : 'gray')
                    ->visible(fn () => true),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->getFullPath()),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->sortable(),

                Tables\Columns\TextColumn::make('size')
                    ->formatStateUsing(fn ($record): string => $record->getFormattedSize())
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->formatStateUsing(fn ($record): string => $record->getFormattedDuration())
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(FileSystemItemType::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),

                Tables\Filters\SelectFilter::make('file_type')
                    ->options(collect(FileType::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Folder')
                    ->relationship('parent', 'name', fn ($query) => $query->where('type', FileSystemItemType::Folder->value))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFileSystemItems::route('/'),
            'create' => Pages\CreateFileSystemItem::route('/create'),
            'edit' => Pages\EditFileSystemItem::route('/{record}/edit'),
        ];
    }
}
