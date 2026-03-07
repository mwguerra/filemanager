<?php

namespace MWGuerra\FileManager\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface FileSystemItemInterface
{
    /**
     * Get the parent folder relationship.
     */
    public function parent(): BelongsTo;

    /**
     * Get child items relationship (for folders).
     */
    public function children(): HasMany;

    /**
     * Check if this item is a folder.
     */
    public function isFolder(): bool;

    /**
     * Check if this item is a file (any type).
     */
    public function isFile(): bool;

    /**
     * Check if this item is a video file.
     */
    public function isVideo(): bool;

    /**
     * Check if this item is an image file.
     */
    public function isImage(): bool;

    /**
     * Check if this item is a document file.
     */
    public function isDocument(): bool;

    /**
     * Check if this item is an audio file.
     */
    public function isAudio(): bool;

    /**
     * Get all ancestors (path to root).
     */
    public function ancestors(): array;

    /**
     * Get the full hierarchical path for this item.
     * Computed dynamically from parent relationships.
     */
    public function getFullPath(): string;

    /**
     * Get the depth level of this item in the hierarchy.
     * Root items have depth 0.
     */
    public function getDepth(): int;

    /**
     * Move item to a new parent folder.
     * @param FileSystemItemInterface|null $newParent
     * @return bool|string Returns true on success, or an error message string on failure
     */
    public function moveTo($newParent): bool|string;

    /**
     * Count direct files (non-folders) in this folder only.
     */
    public function getDirectFileCount(): int;

    /**
     * Format file size for display.
     */
    public function getFormattedSize(): string;

    /**
     * Format duration for display.
     */
    public function getFormattedDuration(): string;

    /**
     * Get folder tree structure for sidebar.
     */
    public static function getFolderTree(int|string|null $parentId = null): array;

    /**
     * Get items in a folder (by parent_id).
     */
    public static function getItemsInFolder(int|string|null $parentId = null): Collection;

    /**
     * Count direct files in a folder (static version for null parent).
     */
    public static function getDirectFileCountForFolder(int|string|null $folderId): int;

    /**
     * Determine file type from mime type.
     */
    public static function determineFileType(string $mimeType): string;
}
