<?php

namespace MWGuerra\FileManager\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface for file manager adapters.
 *
 * Adapters abstract the underlying data source for the file manager,
 * allowing it to work with different backends (database, storage, etc.).
 */
interface FileManagerAdapterInterface
{
    /**
     * Get items in a folder.
     *
     * @param  string|null  $path  The folder path (null for root)
     * @return Collection<FileManagerItemInterface>
     */
    public function getItems(?string $path = null): Collection;

    /**
     * Get folders only (for tree navigation).
     *
     * @param  string|null  $path  The folder path (null for root)
     * @return Collection<FileManagerItemInterface>
     */
    public function getFolders(?string $path = null): Collection;

    /**
     * Get an item by its path or identifier.
     *
     * @param  string  $identifier  Path or ID depending on adapter
     */
    public function getItem(string $identifier): ?FileManagerItemInterface;

    /**
     * Get the folder tree structure.
     *
     * For performance with remote storage (S3), this may return only root-level
     * folders with children loaded lazily via getFolderChildren().
     *
     * @param bool $lazy If true, only load root folders (children loaded on-demand)
     * @return array Nested array of folders
     */
    public function getFolderTree(bool $lazy = false): array;

    /**
     * Get immediate children of a folder (for lazy loading).
     *
     * This method is optimized for remote storage by only loading one level deep.
     * Each child includes a 'has_children' flag to indicate if it has subfolders.
     *
     * @param string|null $path The folder path (null for root)
     * @return array Array of folder data with id, name, path, has_children
     */
    public function getFolderChildren(?string $path = null): array;

    /**
     * Get breadcrumbs for a path.
     *
     * @param  string|null  $path  Current path
     * @return array Array of breadcrumb items with 'id', 'name', 'path' keys
     */
    public function getBreadcrumbs(?string $path): array;

    /**
     * Create a new folder.
     *
     * @param  string  $name  Folder name
     * @param  string|null  $parentPath  Parent folder path
     * @return FileManagerItemInterface|string The created item or error message
     */
    public function createFolder(string $name, ?string $parentPath = null): FileManagerItemInterface|string;

    /**
     * Upload a file.
     *
     * @param  mixed  $file  The uploaded file
     * @param  string|null  $path  Target folder path
     * @return FileManagerItemInterface|string The created item or error message
     */
    public function uploadFile(mixed $file, ?string $path = null): FileManagerItemInterface|string;

    /**
     * Rename an item.
     *
     * @param  string  $identifier  Item path or ID
     * @param  string  $newName  New name
     * @return bool|string True on success, error message on failure
     */
    public function rename(string $identifier, string $newName): bool|string;

    /**
     * Move an item to a new location.
     *
     * @param  string  $identifier  Item path or ID
     * @param  string|null  $newParentPath  New parent folder path
     * @return bool|string True on success, error message on failure
     */
    public function move(string $identifier, ?string $newParentPath): bool|string;

    /**
     * Delete an item.
     *
     * @param  string  $identifier  Item path or ID
     * @return bool|string True on success, error message on failure
     */
    public function delete(string $identifier): bool|string;

    /**
     * Delete multiple items.
     *
     * @param  array  $identifiers  Array of paths or IDs
     * @return int Number of items deleted
     */
    public function deleteMany(array $identifiers): int;

    /**
     * Check if an item exists.
     *
     * @param  string  $identifier  Item path or ID
     */
    public function exists(string $identifier): bool;

    /**
     * Get the URL for a file.
     *
     * @param  string  $identifier  Item path or ID
     */
    public function getUrl(string $identifier): ?string;

    /**
     * Get file contents (for text preview).
     *
     * @param  string  $identifier  Item path or ID
     * @param  int  $maxSize  Maximum size to read
     */
    public function getContents(string $identifier, int $maxSize = 1048576): ?string;

    /**
     * Get a read stream for a file.
     *
     * Returns a PHP stream resource for memory-efficient file reading.
     * Useful for large file downloads without loading entire file into memory.
     *
     * @param  string  $identifier  Item path or ID
     * @return resource|null The stream resource or null if not available
     */
    public function getStream(string $identifier): mixed;

    /**
     * Get the file size in bytes.
     *
     * @param  string  $identifier  Item path or ID
     * @return int|null File size or null if not available
     */
    public function getSize(string $identifier): ?int;

    /**
     * Get the mode name for this adapter.
     */
    public function getModeName(): string;
}
