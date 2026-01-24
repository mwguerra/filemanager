<?php

namespace MWGuerra\FileManager\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use MWGuerra\FileManager\Adapters\AdapterFactory;
use MWGuerra\FileManager\Traits\DetectsS3TempUploads;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\Contracts\FileManagerItemInterface;
use MWGuerra\FileManager\Contracts\FileTypeContract;
use MWGuerra\FileManager\FileManagerPlugin;
use MWGuerra\FileManager\FileTypeRegistry;
use MWGuerra\FileManager\Services\AuthorizationService;

class FileManager extends Page
{
    use DetectsS3TempUploads;
    use WithFileUploads;

    protected string $view = 'filemanager::filament.pages.file-manager';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return FileManagerPlugin::current()?->getFileManagerNavigationIcon()
            ?? config('filemanager.file_manager.navigation.icon', 'heroicon-o-folder');
    }

    public function getTitle(): string|Htmlable
    {
        return FileManagerPlugin::current()?->getFileManagerNavigationLabel()
            ?? config('filemanager.file_manager.navigation.label', 'File Manager');
    }

    public static function getNavigationLabel(): string
    {
        return FileManagerPlugin::current()?->getFileManagerNavigationLabel()
            ?? config('filemanager.file_manager.navigation.label', 'File Manager');
    }

    public static function getNavigationSort(): ?int
    {
        return FileManagerPlugin::current()?->getFileManagerNavigationSort()
            ?? config('filemanager.file_manager.navigation.sort', 1);
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return FileManagerPlugin::get()->getFileManagerNavigationGroup();
        } catch (\Throwable) {
            return config('filemanager.file_manager.navigation.group', 'FileManager');
        }
    }

    /**
     * Check if the page sidebar should be shown.
     */
    public function shouldShowPageSidebar(): bool
    {
        return FileManagerPlugin::current()?->isFileManagerPageSidebarEnabled() ?? true;
    }

    /**
     * Get the sidebar root label.
     */
    public function getSidebarRootLabel(): string
    {
        return FileManagerPlugin::current()?->getFileManagerSidebarRootLabel() ?? 'Root';
    }

    /**
     * Get the sidebar heading.
     */
    public function getSidebarHeading(): string
    {
        return FileManagerPlugin::current()?->getFileManagerSidebarHeading() ?? 'Folders';
    }

    /**
     * Check if the user can access this page.
     */
    public static function canAccess(): bool
    {
        return app(AuthorizationService::class)->canViewAny();
    }

    /**
     * Get the authorization service instance.
     */
    protected function getAuthorizationService(): AuthorizationService
    {
        return app(AuthorizationService::class);
    }

    // State properties - using string identifiers for flexibility
    #[\Livewire\Attributes\Url(as: 'path')]
    public ?string $currentPath = null;
    public string $viewMode = 'grid';
    public array $selectedItems = [];
    public array $expandedFolders = [];

    /**
     * Cached folder children for lazy loading (storage mode).
     * Keys are folder IDs, values are arrays of children.
     */
    public array $folderChildrenCache = [];

    // Form properties
    public string $newFolderName = '';
    public ?string $moveTargetPath = null;
    public ?string $itemToMoveId = null;
    public array $itemsToMove = [];

    // Subfolder creation properties
    public ?string $subfolderParentPath = null;
    public string $subfolderName = '';

    // Rename properties
    public ?string $itemToRenameId = null;
    public string $renameItemName = '';

    // Upload properties
    public array $uploadedFiles = [];
    public $uploadedFile = null; // Single file upload (when S3 temp disk)

    // Preview properties
    public ?string $previewItemId = null;

    /**
     * Get the adapter for the current mode.
     */
    protected function getAdapter(): FileManagerAdapterInterface
    {
        return AdapterFactory::make();
    }

    /**
     * Get the current mode.
     */
    public function getMode(): string
    {
        return config('filemanager.mode', 'database');
    }

    /**
     * Check if in storage mode.
     */
    public function isStorageMode(): bool
    {
        return $this->getMode() === 'storage';
    }

    /**
     * Check if in database mode.
     */
    public function isDatabaseMode(): bool
    {
        return $this->getMode() === 'database';
    }

    /**
     * Check if the file manager is in read-only mode.
     * Read-only mode disables create, upload, delete, move, and rename operations.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Get the file type registry instance.
     */
    protected function getFileTypeRegistry(): FileTypeRegistry
    {
        return app(FileTypeRegistry::class);
    }

    /**
     * Get the file type for a given item.
     */
    public function getFileTypeForItem(FileManagerItemInterface $item): FileTypeContract
    {
        return $this->getFileTypeRegistry()->fromFilename($item->getName());
    }

    /**
     * Get the file type for the preview item.
     */
    public function getPreviewFileTypeProperty(): ?FileTypeContract
    {
        $item = $this->previewItem;

        if (!$item) {
            return null;
        }

        return $this->getFileTypeForItem($item);
    }

    public function mount(): void
    {
        $this->expandedFolders = ['root'];

        // If navigating to a specific folder, expand its parent folders
        if ($this->currentPath !== null) {
            $this->expandParentFolders($this->currentPath);
        }
    }

    /**
     * Listen for folder change events from other components (like panel sidebar).
     */
    #[On('filemanager-folder-changed')]
    public function onFolderChanged(): void
    {
        // Clear the folder children cache so fresh data is loaded
        $this->folderChildrenCache = [];
    }

    /**
     * Expand all parent folders of the given folder ID.
     */
    protected function expandParentFolders(?string $folderId): void
    {
        if ($folderId === null) {
            return;
        }

        $item = $this->getAdapter()->getItem($folderId);
        if (!$item) {
            return;
        }

        // Add the current folder to expanded
        if (!in_array($folderId, $this->expandedFolders)) {
            $this->expandedFolders[] = $folderId;
        }

        // Recursively expand parent folders
        $parentPath = $item->getParentPath();
        if ($parentPath !== null) {
            $this->expandParentFolders($parentPath);
        }
    }

    /**
     * Handle single file upload (when S3 temp disk is detected).
     * Converts single file to array for consistent handling.
     */
    public function updatedUploadedFile(): void
    {
        if ($this->uploadedFile) {
            $this->uploadedFiles = [$this->uploadedFile];
            $this->uploadedFile = null;
            $this->updatedUploadedFiles();
        }
    }

    /**
     * Validate uploaded files when they change.
     */
    public function updatedUploadedFiles(): void
    {
        $maxSize = config('filemanager.upload.max_file_size', 100 * 1024);
        $maxSizeMB = round($maxSize / 1024, 1);
        $errors = [];

        foreach ($this->uploadedFiles as $index => $file) {
            $fileSizeKB = $file->getSize() / 1024;

            if ($fileSizeKB > $maxSize) {
                $fileSizeMB = round($fileSizeKB / 1024, 1);
                $errors[] = "{$file->getClientOriginalName()} ({$fileSizeMB}MB) exceeds the {$maxSizeMB}MB limit";
                unset($this->uploadedFiles[$index]);
            }
        }

        $this->uploadedFiles = array_values($this->uploadedFiles);

        if (!empty($errors)) {
            Notification::make()
                ->title('Some files were rejected')
                ->body(implode("\n", $errors))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Get items in the current folder.
     */
    public function getItemsProperty(): Collection
    {
        return $this->getAdapter()->getItems($this->currentPath);
    }

    /**
     * Check if we should use lazy loading for the folder tree.
     *
     * Lazy loading is recommended for storage mode (S3, remote storage)
     * to avoid excessive API calls on page load.
     */
    protected function shouldUseLazyLoading(): bool
    {
        return $this->isStorageMode();
    }

    /**
     * Get folder tree for sidebar.
     *
     * For storage mode (S3), uses lazy loading to minimize API calls.
     * Children are loaded on-demand when folders are expanded.
     */
    public function getFolderTreeProperty(): array
    {
        $useLazy = $this->shouldUseLazyLoading();
        $tree = $this->getAdapter()->getFolderTree($useLazy);

        if ($useLazy) {
            // Merge in any already-loaded children from cache
            return $this->mergeLoadedChildren($tree);
        }

        return $tree;
    }

    /**
     * Merge cached children into the folder tree.
     */
    protected function mergeLoadedChildren(array $folders): array
    {
        return array_map(function ($folder) {
            $folderId = (string) $folder['id'];

            // Check if we have cached children for this folder
            if (isset($this->folderChildrenCache[$folderId])) {
                $folder['children'] = $this->mergeLoadedChildren($this->folderChildrenCache[$folderId]);
                $folder['children_loaded'] = true;
                $folder['has_children'] = !empty($folder['children']);
            }

            return $folder;
        }, $folders);
    }

    /**
     * Load children for a folder (lazy loading).
     *
     * Called when a folder is expanded in lazy loading mode.
     */
    public function loadFolderChildren(string $folderId): void
    {
        // Skip if already loaded
        if (isset($this->folderChildrenCache[$folderId])) {
            return;
        }

        // Load children from adapter
        $children = $this->getAdapter()->getFolderChildren($folderId);
        $this->folderChildrenCache[$folderId] = $children;
    }

    /**
     * Get breadcrumbs for current path.
     */
    public function getBreadcrumbsProperty(): array
    {
        return $this->getAdapter()->getBreadcrumbs($this->currentPath);
    }

    /**
     * Navigate to a folder.
     */
    public function navigateTo(?string $path): void
    {
        $this->currentPath = $path;
        $this->selectedItems = [];
    }

    /**
     * Navigate to folder by ID (for database mode compatibility).
     */
    public function navigateToId(?int $folderId): void
    {
        $this->currentPath = $folderId ? (string) $folderId : null;
        $this->selectedItems = [];
    }

    /**
     * Toggle folder expansion in sidebar.
     *
     * In lazy loading mode, loads children when expanding a folder.
     */
    public function toggleFolder(?string $folderId): void
    {
        $key = $folderId ?? 'root';

        if (in_array($key, $this->expandedFolders)) {
            // Collapsing - just remove from expanded list
            $this->expandedFolders = array_values(array_diff($this->expandedFolders, [$key]));
        } else {
            // Expanding - add to list and load children if using lazy loading
            $this->expandedFolders[] = $key;

            // Load children on-demand for lazy loading mode
            if ($this->shouldUseLazyLoading() && $folderId !== null) {
                $this->loadFolderChildren($folderId);
            }
        }
    }

    /**
     * Check if folder is expanded.
     */
    public function isFolderExpanded(?string $folderId): bool
    {
        $key = $folderId ?? 'root';

        return in_array($key, $this->expandedFolders);
    }

    /**
     * Toggle view mode between grid and list.
     */
    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    /**
     * Toggle item selection.
     */
    public function toggleSelection(string $itemId, bool $multi = false): void
    {
        if ($multi) {
            if (in_array($itemId, $this->selectedItems)) {
                $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
            } else {
                $this->selectedItems[] = $itemId;
            }
        } else {
            if (count($this->selectedItems) === 1 && $this->selectedItems[0] === $itemId) {
                $this->selectedItems = [];
            } else {
                $this->selectedItems = [$itemId];
            }
        }
    }

    /**
     * Clear selection.
     */
    public function clearSelection(): void
    {
        $this->selectedItems = [];
    }

    /**
     * Select all items in the current view.
     */
    public function selectAll(): void
    {
        $this->selectedItems = $this->items->map(fn ($item) => $item->getIdentifier())->toArray();
    }

    /**
     * Check if all items are selected.
     */
    public function allSelected(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return count($this->selectedItems) === $this->items->count();
    }

    /**
     * Check if item is selected.
     */
    public function isSelected(string $itemId): bool
    {
        return in_array($itemId, $this->selectedItems);
    }

    /**
     * Handle item click - navigate for folders, open preview for files.
     */
    public function handleItemClick(string $itemId, bool $ctrlKey = false): void
    {
        $item = $this->getAdapter()->getItem($itemId);

        if (!$item) {
            Notification::make()
                ->title('Item not found')
                ->body('This item may have been moved or deleted.')
                ->warning()
                ->send();
            return;
        }

        if ($item->isFolder()) {
            $this->navigateTo($itemId);
            if (!in_array($itemId, $this->expandedFolders)) {
                $this->expandedFolders[] = $itemId;
            }
        } else {
            $this->openPreview($itemId);
        }
    }

    /**
     * Open preview modal for a file.
     */
    public function openPreview(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);

        if (!$item || $item->isFolder()) {
            return;
        }

        $this->previewItemId = $itemId;
        $this->dispatch('open-modal', id: 'preview-modal');
    }

    /**
     * Close preview modal.
     */
    public function closePreview(): void
    {
        $this->previewItemId = null;
        $this->dispatch('close-modal', id: 'preview-modal');
    }

    /**
     * Get the item being previewed.
     */
    public function getPreviewItemProperty(): ?FileManagerItemInterface
    {
        if (!$this->previewItemId) {
            return null;
        }

        return $this->getAdapter()->getItem($this->previewItemId);
    }

    /**
     * Get the URL for previewing a file.
     */
    public function getPreviewUrl(): ?string
    {
        if (!$this->previewItemId) {
            return null;
        }

        return $this->getAdapter()->getUrl($this->previewItemId);
    }

    /**
     * Get file content for text preview.
     */
    public function getTextContent(): ?string
    {
        $item = $this->previewItem;

        if (!$item) {
            return null;
        }

        $fileType = $this->getFileTypeForItem($item);

        if ($fileType->identifier() !== 'text') {
            return null;
        }

        $maxSize = $fileType->metadata()['max_preview_size'] ?? 1024 * 1024;

        return $this->getAdapter()->getContents($this->previewItemId, $maxSize);
    }

    /**
     * Create a new folder.
     */
    public function createFolder(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()
                ->title('You are not authorized to create folders')
                ->danger()
                ->send();
            return;
        }

        $this->validate([
            'newFolderName' => 'required|string|max:255',
        ]);

        $result = $this->getAdapter()->createFolder($this->newFolderName, $this->currentPath);

        if (is_string($result)) {
            Notification::make()
                ->title($result)
                ->danger()
                ->send();

            return;
        }

        $this->newFolderName = '';

        Notification::make()
            ->title('Folder created successfully')
            ->success()
            ->send();

        $this->dispatch('close-modal', id: 'create-folder-modal');

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    /**
     * Delete selected items.
     */
    public function deleteSelected(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        if (!$this->getAuthorizationService()->canDeleteAny()) {
            Notification::make()
                ->title('You are not authorized to delete items')
                ->danger()
                ->send();
            return;
        }

        $count = $this->getAdapter()->deleteMany($this->selectedItems);
        $this->selectedItems = [];

        Notification::make()
            ->title($count . ' item(s) deleted')
            ->success()
            ->send();

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    /**
     * Delete a single item.
     */
    public function deleteItem(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);

        if (!$item) {
            Notification::make()
                ->title('Item not found')
                ->body('This item may have been moved or deleted.')
                ->warning()
                ->send();
            return;
        }

        if (!$this->getAuthorizationService()->canDelete(null, $item)) {
            Notification::make()
                ->title('You are not authorized to delete this item')
                ->danger()
                ->send();
            return;
        }

        $result = $this->getAdapter()->delete($itemId);

        if ($result === true) {
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));

            Notification::make()
                ->title('Item deleted')
                ->success()
                ->send();

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()
                ->title(is_string($result) ? $result : 'Failed to delete item')
                ->danger()
                ->send();
        }
    }

    /**
     * Open move dialog for an item.
     */
    public function openMoveDialog(string $itemId): void
    {
        $this->itemToMoveId = $itemId;
        $this->itemsToMove = [];
        $this->moveTargetPath = null;
        $this->dispatch('open-modal', id: 'move-item-modal');
    }

    /**
     * Open move dialog for selected items.
     */
    public function openMoveDialogForSelected(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->itemsToMove = $this->selectedItems;
        $this->itemToMoveId = null;
        $this->moveTargetPath = null;
        $this->dispatch('open-modal', id: 'move-item-modal');
    }

    /**
     * Move selected items to target folder.
     */
    public function moveSelected(): void
    {
        if (empty($this->itemsToMove)) {
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($this->itemsToMove as $itemId) {
            $item = $this->getAdapter()->getItem($itemId);

            if (!$item) {
                $failCount++;
                continue;
            }

            if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
                $failCount++;
                continue;
            }

            $result = $this->getAdapter()->move($itemId, $this->moveTargetPath);

            if ($result === true) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        if ($successCount > 0) {
            Notification::make()
                ->title("{$successCount} item(s) moved successfully")
                ->success()
                ->send();
        }

        if ($failCount > 0) {
            Notification::make()
                ->title("{$failCount} item(s) could not be moved")
                ->warning()
                ->send();
        }

        $this->itemsToMove = [];
        $this->moveTargetPath = null;
        $this->selectedItems = [];
        $this->dispatch('close-modal', id: 'move-item-modal');

        // Dispatch event to update other components (like panel sidebar)
        if ($successCount > 0) {
            $this->dispatch('filemanager-folder-changed');
        }
    }

    /**
     * Open create subfolder dialog.
     */
    public function openCreateSubfolderDialog(string $parentPath): void
    {
        $this->subfolderParentPath = $parentPath;
        $this->subfolderName = '';
        $this->dispatch('open-modal', id: 'create-subfolder-modal');
    }

    /**
     * Create a subfolder in a specific parent folder.
     */
    public function createSubfolder(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()
                ->title('You are not authorized to create folders')
                ->danger()
                ->send();
            return;
        }

        $this->validate([
            'subfolderName' => 'required|string|max:255',
        ]);

        $result = $this->getAdapter()->createFolder($this->subfolderName, $this->subfolderParentPath);

        if (is_string($result)) {
            Notification::make()
                ->title($result)
                ->danger()
                ->send();

            return;
        }

        if ($this->subfolderParentPath && !in_array($this->subfolderParentPath, $this->expandedFolders)) {
            $this->expandedFolders[] = $this->subfolderParentPath;
        }

        $this->subfolderName = '';
        $this->subfolderParentPath = null;

        Notification::make()
            ->title('Subfolder created successfully')
            ->success()
            ->send();

        $this->dispatch('close-modal', id: 'create-subfolder-modal');

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    /**
     * Open rename dialog for an item.
     */
    public function openRenameDialog(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);

        if ($item) {
            $this->itemToRenameId = $itemId;
            $this->renameItemName = $item->getName();
            $this->dispatch('open-modal', id: 'rename-item-modal');
        }
    }

    /**
     * Rename an item.
     */
    public function renameItem(): void
    {
        if (!$this->itemToRenameId) {
            return;
        }

        $item = $this->getAdapter()->getItem($this->itemToRenameId);

        if (!$item) {
            Notification::make()
                ->title('Item not found')
                ->body('This item may have been moved or deleted.')
                ->warning()
                ->send();
            $this->dispatch('close-modal', id: 'rename-item-modal');
            return;
        }

        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()
                ->title('You are not authorized to rename this item')
                ->danger()
                ->send();
            return;
        }

        $this->validate([
            'renameItemName' => 'required|string|max:255',
        ]);

        $result = $this->getAdapter()->rename($this->itemToRenameId, $this->renameItemName);

        if ($result === true) {
            $this->itemToRenameId = null;
            $this->renameItemName = '';

            Notification::make()
                ->title('Item renamed successfully')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'rename-item-modal');

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()
                ->title(is_string($result) ? $result : 'Failed to rename item')
                ->danger()
                ->send();
        }
    }

    /**
     * Upload files to the current folder.
     */
    public function uploadFiles(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()
                ->title('You are not authorized to upload files')
                ->danger()
                ->send();
            return;
        }

        if (empty($this->uploadedFiles)) {
            Notification::make()
                ->title('No files selected')
                ->warning()
                ->send();

            return;
        }

        $security = app(\MWGuerra\FileManager\Services\FileSecurityService::class);
        $uploadCount = 0;
        $errors = [];

        foreach ($this->uploadedFiles as $file) {
            // Validate file security
            $validation = $security->validateUpload($file);

            if (!$validation['valid']) {
                $errors[] = $file->getClientOriginalName() . ': ' . $validation['error'];
                continue;
            }

            $result = $this->getAdapter()->uploadFile($file, $this->currentPath);

            if (!is_string($result)) {
                $uploadCount++;
            } else {
                $errors[] = $file->getClientOriginalName() . ': ' . $result;
            }
        }

        $this->uploadedFiles = [];

        if ($uploadCount > 0) {
            Notification::make()
                ->title($uploadCount . ' file(s) uploaded successfully')
                ->success()
                ->send();
        }

        if (!empty($errors)) {
            Notification::make()
                ->title('Some files could not be uploaded')
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->danger()
                ->send();
        }

        $this->dispatch('close-modal', id: 'upload-files-modal');
    }

    /**
     * Clear uploaded files.
     */
    public function clearUploadedFiles(): void
    {
        $this->uploadedFiles = [];
    }

    /**
     * Set move target folder.
     */
    public function setMoveTarget(?string $path): void
    {
        $this->moveTargetPath = $path;
    }

    /**
     * Execute move operation.
     */
    public function moveItem(): void
    {
        if ($this->itemToMoveId === null) {
            return;
        }

        $item = $this->getAdapter()->getItem($this->itemToMoveId);

        if (!$item) {
            Notification::make()
                ->title('Item not found')
                ->body('This item may have been moved or deleted.')
                ->warning()
                ->send();
            $this->dispatch('close-modal', id: 'move-item-modal');
            return;
        }

        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()
                ->title('You are not authorized to move this item')
                ->danger()
                ->send();
            return;
        }

        $result = $this->getAdapter()->move($this->itemToMoveId, $this->moveTargetPath);

        if ($result === true) {
            Notification::make()
                ->title('Item moved successfully')
                ->success()
                ->send();

            $this->itemToMoveId = null;
            $this->moveTargetPath = null;
            $this->dispatch('close-modal', id: 'move-item-modal');

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()
                ->title(is_string($result) ? $result : 'Failed to move item')
                ->danger()
                ->send();
        }
    }

    /**
     * Handle drag and drop.
     */
    public function handleDrop(string $targetId, string $draggedId): void
    {
        $target = $this->getAdapter()->getItem($targetId);
        $dragged = $this->getAdapter()->getItem($draggedId);

        if (!$target || !$dragged || !$target->isFolder()) {
            return;
        }

        if ($targetId === $draggedId) {
            return;
        }

        $this->itemToMoveId = $draggedId;
        $this->moveTargetPath = $targetId;
        $this->moveItem();
    }

    /**
     * Get all folders for move dialog tree.
     */
    public function getAllFoldersProperty(): Collection
    {
        return $this->getAdapter()->getFolders();
    }

    /**
     * Get the item being moved.
     */
    public function getItemToMoveProperty(): ?FileManagerItemInterface
    {
        if (!$this->itemToMoveId) {
            return null;
        }

        return $this->getAdapter()->getItem($this->itemToMoveId);
    }

    /**
     * Get the parent folder for subfolder creation.
     */
    public function getSubfolderParentProperty(): ?FileManagerItemInterface
    {
        if (!$this->subfolderParentPath) {
            return null;
        }

        return $this->getAdapter()->getItem($this->subfolderParentPath);
    }

    /**
     * Get the item being renamed.
     */
    public function getItemToRenameProperty(): ?FileManagerItemInterface
    {
        if (!$this->itemToRenameId) {
            return null;
        }

        return $this->getAdapter()->getItem($this->itemToRenameId);
    }

    /**
     * Get root file count in a mode-agnostic way.
     */
    public function getRootFileCountProperty(): int
    {
        $items = $this->getAdapter()->getItems(null);

        return $items->filter(fn ($item) => $item->isFile())->count();
    }

    /**
     * Refresh/reload the file manager data.
     * This re-fetches all items from the adapter.
     */
    public function refresh(): void
    {
        $this->dispatch('$refresh');

        Notification::make()
            ->title('File manager refreshed')
            ->success()
            ->send();
    }

    /**
     * Get storage disk info (for storage mode).
     */
    public function getStorageDiskProperty(): ?string
    {
        if (!$this->isStorageMode()) {
            return null;
        }

        return config('filemanager.storage_mode.disk', 'public');
    }

}
