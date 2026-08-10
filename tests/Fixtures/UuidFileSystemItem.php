<?php

namespace MWGuerra\FileManager\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MWGuerra\FileManager\Contracts\FileSystemItemInterface;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;

/**
 * FileSystemItem model with UUID support for testing.
 */
class UuidFileSystemItem extends Model implements FileSystemItemInterface
{
    use HasFactory;
    use HasUuids;

    protected $table = 'uuid_file_system_items';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'type',
        'file_type',
        'parent_id',
        'size',
        'duration',
        'thumbnail',
        'storage_path',
    ];

    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
    ];

    protected static function newFactory(): Factory
    {
        return UuidFileSystemItemFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function getType(): FileSystemItemType
    {
        return FileSystemItemType::from($this->type);
    }

    public function getFileType(): ?FileType
    {
        return $this->file_type ? FileType::from($this->file_type) : null;
    }

    public function isFolder(): bool
    {
        return $this->type === FileSystemItemType::Folder->value;
    }

    public function isFile(): bool
    {
        return $this->type === FileSystemItemType::File->value;
    }

    public function isVideo(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Video->value;
    }

    public function isImage(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Image->value;
    }

    public function isDocument(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Document->value;
    }

    public function isAudio(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Audio->value;
    }

    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    public function getFullPath(): string
    {
        if ($this->parent_id === null) {
            return '/' . $this->name;
        }

        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return '/' . implode('/', $path);
    }

    public function getDepth(): int
    {
        return count($this->ancestors());
    }

    public function moveTo($newParent): bool|string
    {
        /** @var static|null $newParent */
        if ($this->isFolder() && $newParent) {
            $parentIds = collect($newParent->ancestors())->pluck('id')->push($newParent->id);
            if ($parentIds->contains($this->id)) {
                return 'Cannot move a folder into itself or its descendants';
            }
        }

        $targetParentId = $newParent?->id;
        $existingItem = static::where('parent_id', $targetParentId)
            ->where('name', $this->name)
            ->where('id', '!=', $this->id)
            ->first();

        if ($existingItem) {
            return 'An item with this name already exists in the destination folder';
        }

        $this->parent_id = $targetParentId;

        return $this->save();
    }

    public function getDirectFileCount(): int
    {
        return $this->children()
            ->where('type', '!=', FileSystemItemType::Folder->value)
            ->count();
    }

    public function getFileCount(): int
    {
        $count = $this->getDirectFileCount();

        foreach ($this->children()->where('type', FileSystemItemType::Folder->value)->get() as $folder) {
            $count += $folder->getFileCount();
        }

        return $count;
    }

    public function getFormattedSize(): string
    {
        if (!$this->size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 1) . ' ' . $units[$unitIndex];
    }

    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public static function getDirectFileCountForFolder(int|string|null $folderId): int
    {
        return static::where('parent_id', $folderId)
            ->where('type', '!=', FileSystemItemType::Folder->value)
            ->count();
    }

    public static function getFileCountForFolder(int|string|null $folderId): int
    {
        if ($folderId === null) {
            return static::where('type', '!=', FileSystemItemType::Folder->value)->count();
        }

        $folder = static::find($folderId);

        return $folder ? $folder->getFileCount() : 0;
    }

    public static function getFolderTree(int|string|null $parentId = null): array
    {
        $folders = static::where('type', FileSystemItemType::Folder->value)
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        return $folders->map(fn ($folder) => [
            'id' => $folder->id,
            'name' => $folder->name,
            'children' => static::getFolderTree($folder->id),
            'file_count' => $folder->getDirectFileCount(),
        ])->toArray();
    }

    public static function getItemsInFolder(int|string|null $parentId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('parent_id', $parentId)
            ->orderByRaw("CASE WHEN type = '" . FileSystemItemType::Folder->value . "' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    public static function determineFileType(string $mimeType): string
    {
        return FileType::fromMimeType($mimeType)->value;
    }
}
