<?php

namespace MWGuerra\FileManager\Adapters;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\Contracts\FileManagerItemInterface;

/**
 * Storage adapter for direct file system access.
 *
 * This adapter reads files and folders directly from a Laravel Storage disk.
 * No database is used. Operations like move/rename actually affect the files.
 */
class StorageAdapter implements FileManagerAdapterInterface
{
    /**
     * Maximum recursion depth for folder operations.
     * Prevents stack overflow from deeply nested directories.
     */
    public const MAX_RECURSION_DEPTH = 50;

    protected string $disk;
    protected string $root;
    protected bool $showHidden;

    public function __construct(?string $disk = null, ?string $root = null, ?bool $showHidden = null)
    {
        $this->disk = $disk ?? config('filemanager.storage_mode.disk', 'public');
        $this->root = $root ?? config('filemanager.storage_mode.root', '');
        $this->showHidden = $showHidden ?? config('filemanager.storage_mode.show_hidden', false);
    }

    /**
     * Get the storage instance.
     */
    protected function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Normalize a path relative to root.
     *
     * @throws \InvalidArgumentException If path traversal is detected
     */
    protected function normalizePath(?string $path): string
    {
        if ($path === null || $path === '' || $path === '/') {
            return $this->root;
        }

        // Sanitize the path first
        $path = $this->sanitizePath($path);

        // If the path already starts with the root, don't add it again
        // This prevents double-prefixing when identifiers are full paths
        if ($this->root) {
            $normalizedRoot = rtrim($this->root, '/');
            if (str_starts_with($path, $normalizedRoot . '/') || $path === $normalizedRoot) {
                // Path already includes root, validate and return as-is
                $this->validatePathWithinRoot($path);
                return $path;
            }
            $fullPath = $normalizedRoot . '/' . $path;
        } else {
            $fullPath = $path;
        }

        // Validate the normalized path doesn't escape root
        $this->validatePathWithinRoot($fullPath);

        return $fullPath;
    }

    /**
     * Sanitize a path to prevent traversal attacks.
     */
    protected function sanitizePath(string $path): string
    {
        // Remove leading/trailing whitespace and slashes
        $path = trim($path, " \t\n\r\0\x0B/\\");

        // Decode URL encoding that might hide traversal
        $path = urldecode($path);

        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize path separators
        $path = str_replace('\\', '/', $path);

        // Remove any .. or . components
        $parts = explode('/', $path);
        $safeParts = [];

        foreach ($parts as $part) {
            // Skip empty parts, current directory, and parent directory references
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }

            // Additional check for encoded traversal attempts
            if (preg_match('/^\.{2,}$/', $part)) {
                continue;
            }

            $safeParts[] = $part;
        }

        return implode('/', $safeParts);
    }

    /**
     * Validate that a path stays within the configured root.
     *
     * @throws \InvalidArgumentException If path escapes root
     */
    protected function validatePathWithinRoot(string $path): void
    {
        // If no root is set, any path is valid
        if (empty($this->root)) {
            return;
        }

        // Normalize both paths for comparison
        $normalizedRoot = rtrim($this->root, '/');
        $normalizedPath = rtrim($path, '/');

        // The path must start with the root
        if (!str_starts_with($normalizedPath, $normalizedRoot)) {
            Log::warning('FileManager path traversal attempt detected', [
                'attempted_path' => $path,
                'root' => $this->root,
                'disk' => $this->disk,
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
            throw new \InvalidArgumentException('Path traversal attempt detected');
        }

        // Check that there's either nothing after root, or a slash
        $afterRoot = substr($normalizedPath, strlen($normalizedRoot));
        if ($afterRoot !== '' && !str_starts_with($afterRoot, '/')) {
            Log::warning('FileManager path traversal attempt detected', [
                'attempted_path' => $path,
                'root' => $this->root,
                'disk' => $this->disk,
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
            throw new \InvalidArgumentException('Path traversal attempt detected');
        }
    }

    /**
     * Validate a path is safe (public method for external use).
     */
    public function isPathSafe(string $path): bool
    {
        try {
            $this->normalizePath($path);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get the display path (without root prefix).
     */
    protected function displayPath(string $fullPath): string
    {
        if ($this->root && str_starts_with($fullPath, $this->root)) {
            $path = substr($fullPath, strlen($this->root));
            return ltrim($path, '/');
        }

        return $fullPath;
    }

    /**
     * Check if a name is hidden (starts with .).
     */
    protected function isHidden(string $name): bool
    {
        return str_starts_with($name, '.');
    }

    public function getItems(?string $path = null): Collection
    {
        $fullPath = $this->normalizePath($path);
        $items = collect();

        try {
            // Get directories
            $directories = $this->storage()->directories($fullPath);
            foreach ($directories as $dir) {
                $name = basename($dir);
                if (!$this->showHidden && $this->isHidden($name)) {
                    continue;
                }
                $items->push(StorageItem::fromPath($dir, $this->disk, true));
            }

            // Get files
            $files = $this->storage()->files($fullPath);
            foreach ($files as $file) {
                $name = basename($file);
                if (!$this->showHidden && $this->isHidden($name)) {
                    continue;
                }
                $items->push(StorageItem::fromPath($file, $this->disk, false));
            }
        } catch (\Exception $e) {
            // Return empty collection on error
        }

        // Sort: folders first, then by name
        return $items->sortBy(function (StorageItem $item) {
            return ($item->isFolder() ? '0' : '1') . strtolower($item->getName());
        })->values();
    }

    public function getFolders(?string $path = null): Collection
    {
        $fullPath = $this->normalizePath($path);
        $folders = collect();

        try {
            $directories = $this->storage()->directories($fullPath);
            foreach ($directories as $dir) {
                $name = basename($dir);
                if (!$this->showHidden && $this->isHidden($name)) {
                    continue;
                }
                $folders->push(StorageItem::fromPath($dir, $this->disk, true));
            }
        } catch (\Exception $e) {
            // Return empty collection on error
        }

        return $folders->sortBy(fn (StorageItem $item) => strtolower($item->getName()))->values();
    }

    public function getItem(string $identifier): ?FileManagerItemInterface
    {
        $path = $this->normalizePath($identifier);

        try {
            // Check if it's a directory first
            $parentDir = dirname($path) ?: '.';
            $directories = $this->storage()->directories($parentDir);
            if (in_array($path, $directories)) {
                return StorageItem::fromPath($path, $this->disk, true);
            }

            // Then check if it's a file
            if ($this->storage()->exists($path)) {
                return StorageItem::fromPath($path, $this->disk, false);
            }
        } catch (\Exception $e) {
            // Item not found
        }

        return null;
    }

    public function getFolderTree(bool $lazy = false): array
    {
        if ($lazy) {
            // Lazy mode: only load root-level folders, children loaded on-demand
            return $this->getFolderChildren(null);
        }

        // Full recursive mode (original behavior, avoid for S3)
        return $this->buildFolderTree($this->root, 0);
    }

    /**
     * Get immediate children of a folder (for lazy loading).
     *
     * Optimized for remote storage (S3) - makes only ONE API call to list directories.
     * Does not count files or check for grandchildren to minimize API calls.
     *
     * Performance: For a folder with N subfolders:
     * - Old recursive approach: O(total_folders) API calls
     * - This lazy approach: O(1) API call per level expanded
     *
     * @param string|null $path The folder path (null for root)
     * @return array Array of folder data with id, name, path, has_children
     */
    public function getFolderChildren(?string $path = null): array
    {
        $fullPath = $this->normalizePath($path);
        $children = [];

        try {
            // Single API call to list immediate directories
            $directories = $this->storage()->directories($fullPath);

            foreach ($directories as $dir) {
                $name = basename($dir);
                if (!$this->showHidden && $this->isHidden($name)) {
                    continue;
                }

                $displayPath = $this->displayPath($dir);

                // Assume all folders might have children to avoid extra API calls.
                // The expand chevron will be shown, but if no children exist,
                // it will simply show an empty list when expanded.
                // This trades UX precision for significant performance gains.
                $children[] = [
                    'id' => $displayPath ?: null,
                    'name' => $name,
                    'path' => '/' . $displayPath,
                    'file_count' => 0, // Skip file counting for performance
                    'has_children' => true, // Assume true to avoid extra API calls
                    'children' => [], // Children loaded on-demand
                    'children_loaded' => false,
                ];
            }
        } catch (\Exception $e) {
            // Return empty on error
        }

        // Sort by name
        usort($children, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $children;
    }

    /**
     * Recursively build folder tree with depth limit.
     *
     * WARNING: This method can be slow with remote storage (S3) due to
     * multiple API calls. Use getFolderTree(lazy: true) for better performance.
     *
     * @param string $path The path to build tree from
     * @param int $depth Current recursion depth
     * @return array The folder tree structure
     */
    protected function buildFolderTree(string $path, int $depth = 0): array
    {
        // Prevent stack overflow from deeply nested directories
        if ($depth >= self::MAX_RECURSION_DEPTH) {
            return [];
        }

        $tree = [];

        try {
            $directories = $this->storage()->directories($path);

            foreach ($directories as $dir) {
                $name = basename($dir);
                if (!$this->showHidden && $this->isHidden($name)) {
                    continue;
                }

                $displayPath = $this->displayPath($dir);

                // Count files in this folder (can be slow for S3)
                $fileCount = count(array_filter(
                    $this->storage()->files($dir),
                    fn ($f) => $this->showHidden || !$this->isHidden(basename($f))
                ));

                $children = $this->buildFolderTree($dir, $depth + 1);

                $tree[] = [
                    'id' => $displayPath ?: null,
                    'name' => $name,
                    'path' => '/' . $displayPath,
                    'file_count' => $fileCount,
                    'has_children' => !empty($children),
                    'children' => $children,
                    'children_loaded' => true,
                ];
            }
        } catch (\Exception $e) {
            // Return empty on error
        }

        // Sort by name
        usort($tree, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $tree;
    }

    public function getBreadcrumbs(?string $path): array
    {
        $breadcrumbs = [
            ['id' => null, 'name' => 'Root', 'path' => '/'],
        ];

        if (!$path || $path === '/') {
            return $breadcrumbs;
        }

        $parts = array_filter(explode('/', $path));
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $breadcrumbs[] = [
                'id' => ltrim($currentPath, '/'),
                'name' => $part,
                'path' => $currentPath,
            ];
        }

        return $breadcrumbs;
    }

    public function createFolder(string $name, ?string $parentPath = null): FileManagerItemInterface|string
    {
        $fullParent = $this->normalizePath($parentPath);
        $newPath = $fullParent ? rtrim($fullParent, '/') . '/' . $name : $name;

        // Check if already exists
        if ($this->storage()->exists($newPath) || in_array($newPath, $this->storage()->directories(dirname($newPath) ?: '.'))) {
            return 'A folder with this name already exists';
        }

        try {
            $this->storage()->makeDirectory($newPath);

            Log::info('FileManager folder created', [
                'path' => $newPath,
                'disk' => $this->disk,
                'user_id' => auth()->id(),
            ]);

            return StorageItem::fromPath($newPath, $this->disk, true);
        } catch (\Exception $e) {
            Log::error('FileManager failed to create folder', [
                'path' => $newPath,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return 'Failed to create folder: ' . $e->getMessage();
        }
    }

    public function uploadFile(mixed $file, ?string $path = null): FileManagerItemInterface|string
    {
        $fullPath = $this->normalizePath($path);
        $originalName = $file->getClientOriginalName();

        // Check for duplicate and make unique if needed
        $targetPath = $fullPath ? rtrim($fullPath, '/') . '/' . $originalName : $originalName;

        if ($this->storage()->exists($targetPath)) {
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $originalName = $nameWithoutExt . '_' . time() . '.' . $ext;
            $targetPath = $fullPath ? rtrim($fullPath, '/') . '/' . $originalName : $originalName;
        }

        try {
            $fileSize = $file->getSize();
            $fileMimeType = $file->getMimeType();
            
            $storedPath = $file->storeAs($fullPath ?: '', $originalName, $this->disk);

            Log::info('FileManager file uploaded', [
                'path' => $storedPath,
                'original_name' => $originalName,
                'size' => $fileSize,
                'mime_type' => $fileMimeType,
                'disk' => $this->disk,
                'user_id' => auth()->id(),
            ]);

            return StorageItem::fromPath($storedPath, $this->disk, false);
        } catch (\Exception $e) {
            Log::error('FileManager failed to upload file', [
                'filename' => $originalName,
                'path' => $fullPath,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return 'Failed to upload file: ' . $e->getMessage();
        }
    }

    public function rename(string $identifier, string $newName): bool|string
    {
        $oldPath = $this->normalizePath($identifier);
        $parentDir = dirname($oldPath);
        $newPath = ($parentDir === '.' ? '' : $parentDir . '/') . $newName;

        // Check if new name already exists
        if ($this->storage()->exists($newPath)) {
            return 'An item with this name already exists';
        }

        try {
            // Check if it's a directory
            $isDirectory = in_array($oldPath, $this->storage()->directories($parentDir ?: '.'));

            if ($isDirectory) {
                // For directories, we need to copy all contents and delete old
                $this->copyDirectory($oldPath, $newPath);
                $this->storage()->deleteDirectory($oldPath);
            } else {
                $this->storage()->move($oldPath, $newPath);
            }

            Log::info('FileManager item renamed', [
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'disk' => $this->disk,
                'user_id' => auth()->id(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FileManager failed to rename item', [
                'old_path' => $oldPath,
                'new_name' => $newName,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return 'Failed to rename: ' . $e->getMessage();
        }
    }

    /**
     * Copy a directory recursively with depth limit.
     *
     * @param string $from Source directory path
     * @param string $to Destination directory path
     * @param int $depth Current recursion depth
     * @throws \RuntimeException If max recursion depth is exceeded
     */
    protected function copyDirectory(string $from, string $to, int $depth = 0): void
    {
        // Prevent stack overflow from deeply nested directories
        if ($depth >= self::MAX_RECURSION_DEPTH) {
            throw new \RuntimeException('Maximum directory depth exceeded during copy operation');
        }

        $this->storage()->makeDirectory($to);

        // Copy files
        foreach ($this->storage()->files($from) as $file) {
            $newFile = $to . '/' . basename($file);
            $this->storage()->copy($file, $newFile);
        }

        // Copy subdirectories
        foreach ($this->storage()->directories($from) as $dir) {
            $newDir = $to . '/' . basename($dir);
            $this->copyDirectory($dir, $newDir, $depth + 1);
        }
    }

    public function move(string $identifier, ?string $newParentPath): bool|string
    {
        $oldPath = $this->normalizePath($identifier);
        $name = basename($oldPath);

        $newParent = $this->normalizePath($newParentPath);
        $newPath = $newParent ? rtrim($newParent, '/') . '/' . $name : $name;

        if ($oldPath === $newPath) {
            return 'Item is already in this location';
        }

        // Check if target already exists
        if ($this->storage()->exists($newPath)) {
            return 'An item with this name already exists in the destination';
        }

        // Prevent moving a folder into itself
        if (str_starts_with($newPath . '/', $oldPath . '/')) {
            return 'Cannot move a folder into itself';
        }

        try {
            $parentDir = dirname($oldPath);
            $isDirectory = in_array($oldPath, $this->storage()->directories($parentDir ?: '.'));

            if ($isDirectory) {
                $this->copyDirectory($oldPath, $newPath);
                $this->storage()->deleteDirectory($oldPath);
            } else {
                $this->storage()->move($oldPath, $newPath);
            }

            Log::info('FileManager item moved', [
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'disk' => $this->disk,
                'user_id' => auth()->id(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FileManager failed to move item', [
                'old_path' => $oldPath,
                'new_parent' => $newParentPath,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return 'Failed to move: ' . $e->getMessage();
        }
    }

    public function delete(string $identifier): bool|string
    {
        $path = $this->normalizePath($identifier);

        try {
            $parentDir = dirname($path);
            $isDirectory = in_array($path, $this->storage()->directories($parentDir ?: '.'));

            if ($isDirectory) {
                $this->storage()->deleteDirectory($path);
            } else {
                $this->storage()->delete($path);
            }

            Log::info('FileManager item deleted', [
                'path' => $path,
                'type' => $isDirectory ? 'directory' : 'file',
                'disk' => $this->disk,
                'user_id' => auth()->id(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FileManager failed to delete item', [
                'path' => $path,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return 'Failed to delete: ' . $e->getMessage();
        }
    }

    public function deleteMany(array $identifiers): int
    {
        $deleted = 0;

        foreach ($identifiers as $identifier) {
            $result = $this->delete($identifier);
            if ($result === true) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function exists(string $identifier): bool
    {
        $path = $this->normalizePath($identifier);

        try {
            // Check as file
            if ($this->storage()->exists($path)) {
                return true;
            }

            // Check as directory
            $parentDir = dirname($path);
            return in_array($path, $this->storage()->directories($parentDir ?: '.'));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl(string $identifier): ?string
    {
        $path = $this->normalizePath($identifier);

        try {
            // Check if file exists before generating URL
            if (!$this->storage()->exists($path)) {
                return null;
            }

            $urlService = app(\MWGuerra\FileManager\Services\FileUrlService::class);
            $expiration = config('filemanager.streaming.url_expiration', 60);

            return $urlService->getPreviewUrl(
                disk: $this->disk,
                path: $path,
                mode: 'storage',
                identifier: $identifier,
                expirationMinutes: $expiration
            );
        } catch (\Exception $e) {
            Log::warning('FileManager: Failed to generate URL for file', [
                'disk' => $this->disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getContents(string $identifier, int $maxSize = 1048576): ?string
    {
        $path = $this->normalizePath($identifier);

        try {
            if (!$this->storage()->exists($path)) {
                return null;
            }

            $size = $this->storage()->size($path);

            // Don't read if too large
            if ($size > $maxSize * 2) {
                return null;
            }

            $content = $this->storage()->get($path);

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
        $path = $this->normalizePath($identifier);

        try {
            if (!$this->storage()->exists($path)) {
                return null;
            }

            return $this->storage()->readStream($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSize(string $identifier): ?int
    {
        $path = $this->normalizePath($identifier);

        try {
            if (!$this->storage()->exists($path)) {
                return null;
            }

            return $this->storage()->size($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getModeName(): string
    {
        return 'storage';
    }

    /**
     * Get the disk name.
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Get the root path.
     */
    public function getRoot(): string
    {
        return $this->root;
    }
}
