<?php

namespace MWGuerra\FileManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MWGuerra\FileManager\Contracts\FileSystemItemInterface;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;

/**
 * Default FileSystemItem model for the FileManager package.
 *
 * This model can be extended by applications that need to add custom
 * functionality. Override the 'model' config in filemanager.php to use
 * your custom model class.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $file_type
 * @property int|null $parent_id
 * @property int|null $size
 * @property int|null $duration
 * @property string|null $thumbnail
 * @property string|null $storage_path
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FileSystemItem extends Model implements FileSystemItemInterface
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Override this method in your application's model to use a custom factory.
     */
    protected static function newFactory(): Factory
    {
        // Try to load the package factory - applications should override this
        $factoryClass = 'MWGuerra\\FileManager\\Database\\Factories\\FileSystemItemFactory';
        if (class_exists($factoryClass)) {
            return $factoryClass::new();
        }

        // Fallback: let Laravel's default factory resolution handle it
        return Factory::factoryForModel(static::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * Get the parent folder relationship.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Get child items relationship (for folders).
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Check if this item is a folder.
     */
    public function isFolder(): bool
    {
        return $this->type === FileSystemItemType::Folder->value;
    }

    /**
     * Check if this item is a file (any type).
     */
    public function isFile(): bool
    {
        return $this->type === FileSystemItemType::File->value;
    }

    /**
     * Check if this item is a video file.
     */
    public function isVideo(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Video->value;
    }

    /**
     * Check if this item is an image file.
     */
    public function isImage(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Image->value;
    }

    /**
     * Check if this item is a document file.
     */
    public function isDocument(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Document->value;
    }

    /**
     * Check if this item is an audio file.
     */
    public function isAudio(): bool
    {
        return $this->isFile() && $this->file_type === FileType::Audio->value;
    }

    /**
     * Get all descendants (recursive children).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors (path to root).
     */
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

    /**
     * Get the full hierarchical path for this item.
     * Computed dynamically from parent relationships.
     */
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

    /**
     * Get the depth level of this item in the hierarchy.
     * Root items have depth 0.
     */
    public function getDepth(): int
    {
        return count($this->ancestors());
    }

    /**
     * Get breadcrumb path.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [['id' => null, 'name' => 'Root', 'path' => '/']];

        foreach ($this->ancestors() as $ancestor) {
            $breadcrumbs[] = [
                'id' => $ancestor->id,
                'name' => $ancestor->name,
                'path' => $ancestor->getFullPath(),
            ];
        }

        if ($this->id) {
            $breadcrumbs[] = [
                'id' => $this->id,
                'name' => $this->name,
                'path' => $this->getFullPath(),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Move item to a new parent folder.
     *
     * @param FileSystemItemInterface|null $newParent
     * @return bool|string Returns true on success, or an error message string on failure
     */
    public function moveTo($newParent): bool|string
    {
        /** @var static|null $newParent */
        // Cannot move a folder into itself or its descendants
        if ($this->isFolder() && $newParent) {
            $parentIds = collect($newParent->ancestors())->pluck('id')->push($newParent->id);
            if ($parentIds->contains($this->id)) {
                return 'Cannot move a folder into itself or its descendants';
            }
        }

        // Check for duplicate name in target folder
        $targetParentId = $newParent?->id;
        $existingItem = static::where('parent_id', $targetParentId)
            ->where('name', $this->name)
            ->where('id', '!=', $this->id)
            ->first();

        if ($existingItem) {
            return 'An item with this name already exists in the destination folder';
        }

        // Simply update the parent_id - no path column to update!
        $this->parent_id = $targetParentId;

        return $this->save();
    }

    /**
     * Count direct files (non-folders) in this folder only.
     */
    public function getDirectFileCount(): int
    {
        return static::where('parent_id', $this->id)
            ->where('type', '!=', FileSystemItemType::Folder->value)
            ->count();
    }

    /**
     * Count all files (non-folders) in this folder and all descendants.
     */
    public function getFileCountRecursive(): int
    {
        // Count direct files
        $count = $this->getDirectFileCount();

        // Add files from child folders
        $childFolders = static::where('parent_id', $this->id)
            ->where('type', FileSystemItemType::Folder->value)
            ->get();

        foreach ($childFolders as $childFolder) {
            $count += $childFolder->getFileCountRecursive();
        }

        return $count;
    }

    /**
     * Count direct files in a folder (static version for null parent).
     */
    public static function getDirectFileCountForFolder(int|string|null $folderId): int
    {
        return static::where('parent_id', $folderId)
            ->where('type', '!=', FileSystemItemType::Folder->value)
            ->count();
    }

    /**
     * Count all files in a folder and its descendants (static version for null parent).
     */
    public static function getFileCountForFolder(int|string|null $folderId): int
    {
        if ($folderId === null) {
            // Root: count all files in the system
            return static::where('type', '!=', FileSystemItemType::Folder->value)->count();
        }

        $folder = static::find($folderId);
        return $folder ? $folder->getFileCountRecursive() : 0;
    }

    /**
     * Get folder tree structure for sidebar.
     */
    public static function getFolderTree(int|string|null $parentId = null): array
    {
        $folders = static::where('type', FileSystemItemType::Folder->value)
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        return $folders->map(function ($folder) {
            return [
                'id' => $folder->id,
                'name' => $folder->name,
                'depth' => $folder->getDepth(),
                'file_count' => $folder->getDirectFileCount(),
                'children' => static::getFolderTree($folder->id),
            ];
        })->toArray();
    }

    /**
     * Get items in a folder (by parent_id).
     */
    public static function getItemsInFolder(int|string|null $parentId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('parent_id', $parentId)
            ->orderByRaw("CASE WHEN type = '" . FileSystemItemType::Folder->value . "' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    /**
     * Format file size for display.
     */
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

    /**
     * Format duration for display.
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Determine file type from mime type.
     */
    public static function determineFileType(string $mimeType): string
    {
        return FileType::fromMimeType($mimeType)->value;
    }
}
