<?php

namespace MWGuerra\FileManager\Adapters;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\Contracts\FileManagerItemInterface;
use MWGuerra\FileManager\Contracts\FileSystemItemInterface;

/**
 * Database adapter for file management.
 *
 * This adapter uses an Eloquent model to store file/folder metadata in a database.
 * File contents are stored on a configured storage disk.
 */
class DatabaseAdapter implements FileManagerAdapterInterface
{
    protected string $modelClass;
    protected string $disk;
    protected string $directory;

    public function __construct(?string $modelClass = null, ?string $disk = null, ?string $directory = null)
    {
        $this->modelClass = $modelClass ?? config('filemanager.model');
        $this->disk = $disk ?? config('filemanager.upload.disk', 'public');
        $this->directory = $directory ?? config('filemanager.upload.directory', 'uploads');
    }

    /**
     * Wrap a model in a DatabaseItem.
     */
    protected function wrap(FileSystemItemInterface $model): DatabaseItem
    {
        return new DatabaseItem($model);
    }

    /**
     * Wrap a collection of models.
     */
    protected function wrapCollection($models): Collection
    {
        return collect($models)->map(fn ($model) => $this->wrap($model));
    }

    /**
     * Get the model class.
     */
    protected function model(): string
    {
        return $this->modelClass;
    }

    /**
     * Find a model by ID.
     */
    protected function find(int $id): ?FileSystemItemInterface
    {
        return $this->model()::find($id);
    }

    /**
     * Convert identifier to model.
     * In database mode, identifier is the model ID.
     */
    protected function getModelFromIdentifier(string $identifier): ?FileSystemItemInterface
    {
        if (is_numeric($identifier)) {
            return $this->find((int) $identifier);
        }

        return null;
    }

    /**
     * Convert path to parent ID.
     * For database mode, we need to resolve the path to a folder ID.
     *
     * This method is optimized to avoid N+1 queries by:
     * 1. Short-circuiting for numeric IDs
     * 2. Using recursive path traversal when needed
     */
    protected function pathToFolderId(?string $path): int|string|null
    {
        if ($path === null || $path === '' || $path === '/') {
            return null;
        }

        // Path might be an ID (most common case - short circuit)
        if (is_numeric($path)) {
            return (int) $path;
        }

        // Normalize path
        $path = '/' . ltrim($path, '/');

        // Try to find folder by traversing the path efficiently
        return $this->resolvePathToId($path);
    }

    /**
     * Resolve a path string to a folder ID by traversing the hierarchy.
     *
     * This is more efficient than loading all folders because:
     * 1. It only queries folders at each level of the path
     * 2. It stops as soon as a segment isn't found
     */
    protected function resolvePathToId(string $path): int|string|null
    {
        // Split path into segments
        $segments = array_filter(explode('/', $path), fn ($s) => $s !== '');

        if (empty($segments)) {
            return null;
        }

        $parentId = null;

        foreach ($segments as $segment) {
            $folder = $this->model()::where('type', 'folder')
                ->where('parent_id', $parentId)
                ->where('name', $segment)
                ->first(['id']);

            if (!$folder) {
                return null; // Path segment not found
            }

            $parentId = $folder->id;
        }

        return $parentId;
    }

    public function getItems(?string $path = null): Collection
    {
        $parentId = $this->pathToFolderId($path);
        $items = $this->model()::getItemsInFolder($parentId);

        return $this->wrapCollection($items);
    }

    public function getFolders(?string $path = null): Collection
    {
        $parentId = $this->pathToFolderId($path);

        $folders = $this->model()::where('type', 'folder')
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        return $this->wrapCollection($folders);
    }

    public function getItem(string $identifier): ?FileManagerItemInterface
    {
        $model = $this->getModelFromIdentifier($identifier);

        return $model ? $this->wrap($model) : null;
    }

    public function getFolderTree(bool $lazy = false): array
    {
        if ($lazy) {
            // Lazy mode: only load root-level folders
            return $this->getFolderChildren(null);
        }

        // Full recursive tree (works well for database mode)
        return $this->model()::getFolderTree();
    }

    /**
     * Get immediate children of a folder (for lazy loading).
     *
     * For database mode, this is efficient as it's a single query.
     *
     * @param string|null $path The folder path or ID (null for root)
     * @return array Array of folder data with id, name, path, has_children
     */
    public function getFolderChildren(?string $path = null): array
    {
        $parentId = $this->pathToFolderId($path);

        $folders = $this->model()::where('type', 'folder')
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        return $folders->map(function ($folder) {
            $hasChildren = $this->model()::where('type', 'folder')
                ->where('parent_id', $folder->id)
                ->exists();

            return [
                'id' => (string) $folder->id,
                'name' => $folder->name,
                'path' => $folder->getFullPath(),
                'file_count' => $folder->getDirectFileCount(),
                'has_children' => $hasChildren,
                'children' => [],
                'children_loaded' => false,
            ];
        })->toArray();
    }

    public function getBreadcrumbs(?string $path): array
    {
        $breadcrumbs = [['id' => null, 'name' => 'Root', 'path' => '/']];

        $folderId = $this->pathToFolderId($path);

        if ($folderId) {
            $folder = $this->find($folderId);

            if ($folder) {
                foreach ($folder->ancestors() as $ancestor) {
                    $breadcrumbs[] = [
                        'id' => $ancestor->id,
                        'name' => $ancestor->name,
                        'path' => $ancestor->getFullPath(),
                    ];
                }

                $breadcrumbs[] = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $folder->getFullPath(),
                ];
            }
        }

        return $breadcrumbs;
    }

    public function createFolder(string $name, ?string $parentPath = null): FileManagerItemInterface|string
    {
        $parentId = $this->pathToFolderId($parentPath);

        // Check for duplicate
        $exists = $this->model()::where('parent_id', $parentId)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return 'A folder with this name already exists';
        }

        try {
            $folder = $this->model()::create([
                'name' => $name,
                'type' => 'folder',
                'parent_id' => $parentId,
            ]);

            return $this->wrap($folder);
        } catch (\Exception $e) {
            return 'Failed to create folder: ' . $e->getMessage();
        }
    }

    public function uploadFile(mixed $file, ?string $path = null): FileManagerItemInterface|string
    {
        $parentId = $this->pathToFolderId($path);

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $size = $file->getSize();
        $mimeType = $file->getMimeType();

        try {
            return DB::transaction(function () use ($file, $parentId, $originalName, $extension, $size, $mimeType) {
                // Check for duplicate with lock to prevent race conditions
                $exists = $this->model()::where('parent_id', $parentId)
                    ->where('name', $originalName)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
                    $originalName = $nameWithoutExt . '_' . time() . '.' . $extension;
                }

                // Store file first
                $storedPath = $file->store($this->directory, $this->disk);

                try {
                    // Determine file type
                    $fileType = $this->model()::determineFileType($mimeType);

                    // Create record
                    $item = $this->model()::create([
                        'name' => $originalName,
                        'type' => 'file',
                        'file_type' => $fileType,
                        'parent_id' => $parentId,
                        'size' => $size,
                        'storage_path' => $storedPath,
                    ]);

                    return $this->wrap($item);
                } catch (\Exception $e) {
                    // If database insert fails, clean up the uploaded file
                    try {
                        Storage::disk($this->disk)->delete($storedPath);
                    } catch (\Exception $cleanupEx) {
                        Log::warning('Failed to clean up uploaded file after DB error', [
                            'path' => $storedPath,
                            'error' => $cleanupEx->getMessage(),
                        ]);
                    }
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to upload file', [
                'filename' => $originalName,
                'parentId' => $parentId,
                'error' => $e->getMessage(),
            ]);
            return 'Failed to upload file: ' . $e->getMessage();
        }
    }

    public function rename(string $identifier, string $newName): bool|string
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model) {
            return 'Item not found';
        }

        try {
            return DB::transaction(function () use ($model, $newName) {
                // Lock the row for update to prevent concurrent modifications
                $lockedModel = $this->model()::where('id', $model->id)->lockForUpdate()->first();

                if (!$lockedModel) {
                    throw new \Exception('Item was deleted by another process');
                }

                // Check for duplicate with lock to prevent race conditions
                $exists = $this->model()::where('parent_id', $lockedModel->parent_id)
                    ->where('name', $newName)
                    ->where('id', '!=', $lockedModel->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return 'An item with this name already exists in this folder';
                }

                $lockedModel->name = $newName;
                $lockedModel->save();

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to rename item', [
                'identifier' => $identifier,
                'newName' => $newName,
                'error' => $e->getMessage(),
            ]);
            return 'Failed to rename: ' . $e->getMessage();
        }
    }

    public function move(string $identifier, ?string $newParentPath): bool|string
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model) {
            return 'Item not found';
        }

        $newParentId = $this->pathToFolderId($newParentPath);

        // Same location check
        if ($model->parent_id === $newParentId) {
            return 'Item is already in this folder';
        }

        try {
            return DB::transaction(function () use ($model, $newParentId) {
                // Lock the row for update to prevent concurrent modifications
                $lockedModel = $this->model()::where('id', $model->id)->lockForUpdate()->first();

                if (!$lockedModel) {
                    throw new \Exception('Item was deleted by another process');
                }

                // Get target folder with lock
                $targetFolder = null;
                if ($newParentId) {
                    $targetFolder = $this->model()::where('id', $newParentId)->lockForUpdate()->first();
                    if (!$targetFolder) {
                        return 'Target folder not found';
                    }
                }

                // Prevent moving folder into itself or descendants
                if ($lockedModel->isFolder() && $targetFolder) {
                    $ancestorIds = collect($targetFolder->ancestors())->pluck('id')->toArray();
                    $ancestorIds[] = $targetFolder->id;

                    if (in_array($lockedModel->id, $ancestorIds)) {
                        return 'Cannot move a folder into itself or its descendants';
                    }
                }

                // Check for duplicate name in target folder with lock
                $exists = $this->model()::where('parent_id', $newParentId)
                    ->where('name', $lockedModel->name)
                    ->where('id', '!=', $lockedModel->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return 'An item with this name already exists in the destination folder';
                }

                $lockedModel->parent_id = $newParentId;
                $lockedModel->save();

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to move item', [
                'identifier' => $identifier,
                'newParentPath' => $newParentPath,
                'error' => $e->getMessage(),
            ]);
            return 'Failed to move: ' . $e->getMessage();
        }
    }

    public function delete(string $identifier): bool|string
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model) {
            return 'Item not found';
        }

        try {
            return DB::transaction(function () use ($model) {
                // Lock the row for update to prevent concurrent modifications
                $lockedModel = $this->model()::where('id', $model->id)->lockForUpdate()->first();

                if (!$lockedModel) {
                    throw new \Exception('Item was deleted by another process');
                }

                $storagePath = $lockedModel->storage_path;
                $isFile = $lockedModel->isFile();

                // Delete the database record first
                $lockedModel->delete();

                // Then delete the file from storage
                if ($isFile && $storagePath) {
                    try {
                        Storage::disk($this->disk)->delete($storagePath);
                    } catch (\Exception $e) {
                        // Log the storage delete failure but don't fail the operation
                        // The database record is already deleted
                        Log::warning('Failed to delete file from storage', [
                            'path' => $storagePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to delete item', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
            return 'Failed to delete: ' . $e->getMessage();
        }
    }

    public function deleteMany(array $identifiers): int
    {
        $deleted = 0;

        foreach ($identifiers as $identifier) {
            if ($this->delete($identifier) === true) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function exists(string $identifier): bool
    {
        return $this->getModelFromIdentifier($identifier) !== null;
    }

    public function getUrl(string $identifier): ?string
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model || !$model->storage_path) {
            return null;
        }

        try {
            $urlService = app(\MWGuerra\FileManager\Services\FileUrlService::class);
            $expiration = config('filemanager.streaming.url_expiration', 60);

            return $urlService->getPreviewUrl(
                disk: $this->disk,
                path: $model->storage_path,
                mode: 'database',
                identifier: $identifier,
                expirationMinutes: $expiration
            );
        } catch (\Exception $e) {
            Log::warning('FileManager: Failed to generate URL for file', [
                'disk' => $this->disk,
                'storage_path' => $model->storage_path,
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getContents(string $identifier, int $maxSize = 1048576): ?string
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model || !$model->storage_path) {
            return null;
        }

        try {
            $content = Storage::disk($this->disk)->get($model->storage_path);

            if (strlen($content) > $maxSize) {
                $content = substr($content, 0, $maxSize) . "\n\n... (truncated, file too large for preview)";
            }

            return $content;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getStream(string $identifier): mixed
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model || !$model->storage_path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->readStream($model->storage_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSize(string $identifier): ?int
    {
        $model = $this->getModelFromIdentifier($identifier);

        if (!$model) {
            return null;
        }

        // Use stored size if available
        if ($model->size !== null) {
            return (int) $model->size;
        }

        // Fall back to storage size
        if ($model->storage_path) {
            try {
                return Storage::disk($this->disk)->size($model->storage_path);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public function getModeName(): string
    {
        return 'database';
    }

    /**
     * Get the model class name.
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }
}
