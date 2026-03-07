<?php

namespace MWGuerra\FileManager\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;

class UuidFileSystemItemFactory extends Factory
{
    protected $model = UuidFileSystemItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . '_' . fake()->randomNumber(5),
            'type' => FileSystemItemType::File->value,
            'file_type' => FileType::Document->value,
            'parent_id' => null,
            'size' => fake()->numberBetween(1024, 10485760),
            'duration' => null,
            'thumbnail' => null,
            'storage_path' => 'uploads/' . fake()->uuid() . '.pdf',
        ];
    }

    public function folder(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::Folder->value,
            'file_type' => null,
            'size' => null,
            'storage_path' => null,
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::File->value,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::File->value,
            'file_type' => FileType::Video->value,
            'duration' => fake()->numberBetween(60, 3600),
            'storage_path' => 'uploads/' . fake()->uuid() . '.mp4',
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::File->value,
            'file_type' => FileType::Image->value,
            'thumbnail' => 'thumbnails/' . fake()->uuid() . '.jpg',
            'storage_path' => 'uploads/' . fake()->uuid() . '.jpg',
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::File->value,
            'file_type' => FileType::Audio->value,
            'duration' => fake()->numberBetween(60, 600),
            'storage_path' => 'uploads/' . fake()->uuid() . '.mp3',
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FileSystemItemType::File->value,
            'file_type' => FileType::Document->value,
            'storage_path' => 'uploads/' . fake()->uuid() . '.pdf',
        ]);
    }

    public function inFolder(UuidFileSystemItem $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $folder->id,
        ]);
    }

    public function withParent(int|string|null $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
