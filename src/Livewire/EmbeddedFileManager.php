<?php

namespace MWGuerra\FileManager\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use MWGuerra\FileManager\Adapters\AdapterFactory;
use MWGuerra\FileManager\Traits\DetectsS3TempUploads;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\Contracts\FileManagerItemInterface;
use MWGuerra\FileManager\Contracts\FileTypeContract;
use MWGuerra\FileManager\FileTypeRegistry;
use MWGuerra\FileManager\Services\AuthorizationService;

/**
 * Embeddable File Manager Livewire component.
 *
 * This component provides the full file manager functionality
 * as an embeddable Livewire component for use in forms and pages.
 */
class EmbeddedFileManager extends Component
{
    use DetectsS3TempUploads;
    use WithFileUploads;

    // Configuration properties (set from schema component)
    public string $height = '500px';
    public bool $showHeader = true;
    public bool $showSidebar = true;
    public string $defaultViewMode = 'grid';
    public ?string $disk = null;
    public ?string $target = null;
    public ?string $initialFolder = null;
    public string $sidebarRootLabel = 'Root';
    public string $sidebarHeading = 'Folders';
    public string $breadcrumbsRootLabel = 'Root';

    // State properties
    public ?string $currentPath = null;
    public string $viewMode = 'grid';
    public array $selectedItems = [];
    public array $expandedFolders = [];

    // Change tracking - records all modifications made in the file manager
    public array $changes = [];

    // Form properties
    public string $newFolderName = '';
    public ?string $moveTargetPath = null;
    public ?string $itemToMoveId = null;
    public array $itemsToMove = [];
    public ?string $subfolderParentPath = null;
    public string $subfolderName = '';
    public ?string $itemToRenameId = null;
    public string $renameItemName = '';
    public array $uploadedFiles = [];
    public $uploadedFile = null; // Single file upload (when S3 temp disk)
    public ?string $previewItemId = null;

    public function mount(
        string $height = '500px',
        bool $showHeader = true,
        bool $showSidebar = true,
        string $defaultViewMode = 'grid',
        ?string $disk = null,
        ?string $target = null,
        ?string $initialFolder = null,
        string $sidebarRootLabel = 'Root',
        string $sidebarHeading = 'Folders',
        string $breadcrumbsRootLabel = 'Root'
    ): void {
        $this->height = $height;
        $this->showHeader = $showHeader;
        $this->showSidebar = $showSidebar;
        $this->defaultViewMode = $defaultViewMode;
        $this->viewMode = $defaultViewMode;
        $this->disk = $disk;
        $this->target = $target;
        $this->initialFolder = $initialFolder;
        $this->currentPath = $initialFolder;
        $this->sidebarRootLabel = $sidebarRootLabel;
        $this->sidebarHeading = $sidebarHeading;
        $this->breadcrumbsRootLabel = $breadcrumbsRootLabel;
        $this->expandedFolders = ['root'];

        // Expand parent folders if initialFolder is set
        if ($initialFolder) {
            $this->expandedFolders[] = $initialFolder;
        }
    }

    /**
     * Listen for folder change events from other components (like panel sidebar).
     * This enables bi-directional sync between the embed and the panel sidebar.
     */
    #[On('filemanager-folder-changed')]
    public function onFolderChanged(): void
    {
        // The component will automatically re-render with fresh data
    }

    protected function getAdapter(): FileManagerAdapterInterface
    {
        return AdapterFactory::makeDatabase(
            disk: $this->disk,
            directory: $this->target
        );
    }

    public function getMode(): string
    {
        return 'database';
    }

    /**
     * Check if the component is in read-only mode.
     * Read-only mode disables create, upload, delete, move, and rename operations.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getFileTypeRegistry(): FileTypeRegistry
    {
        return app(FileTypeRegistry::class);
    }

    /**
     * Get the authorization service instance.
     */
    protected function getAuthorizationService(): AuthorizationService
    {
        return app(AuthorizationService::class);
    }

    public function getFileTypeForItem(FileManagerItemInterface $item): FileTypeContract
    {
        return $this->getFileTypeRegistry()->fromFilename($item->getName());
    }

    public function getPreviewFileTypeProperty(): ?FileTypeContract
    {
        $item = $this->previewItem;
        return $item ? $this->getFileTypeForItem($item) : null;
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

    public function getItemsProperty(): Collection
    {
        return $this->getAdapter()->getItems($this->currentPath);
    }

    public function getFolderTreeProperty(): array
    {
        return $this->getAdapter()->getFolderTree();
    }

    public function getBreadcrumbsProperty(): array
    {
        return $this->getAdapter()->getBreadcrumbs($this->currentPath);
    }

    public function navigateTo(?string $path): void
    {
        $this->currentPath = $path;
        $this->selectedItems = [];
    }

    public function toggleFolder(?string $folderId): void
    {
        $key = $folderId ?? 'root';
        if (in_array($key, $this->expandedFolders)) {
            $this->expandedFolders = array_values(array_diff($this->expandedFolders, [$key]));
        } else {
            $this->expandedFolders[] = $key;
        }
    }

    public function isFolderExpanded(?string $folderId): bool
    {
        return in_array($folderId ?? 'root', $this->expandedFolders);
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function isSelected(string $itemId): bool
    {
        return in_array($itemId, $this->selectedItems);
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

    public function openPreview(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);
        if (!$item || $item->isFolder()) return;

        $this->previewItemId = $itemId;
        $this->dispatch('open-modal', id: 'embedded-preview-modal-' . $this->getId());
    }

    public function closePreview(): void
    {
        $this->previewItemId = null;
        $this->dispatch('close-modal', id: 'embedded-preview-modal-' . $this->getId());
    }

    public function getPreviewItemProperty(): ?FileManagerItemInterface
    {
        return $this->previewItemId ? $this->getAdapter()->getItem($this->previewItemId) : null;
    }

    public function getPreviewUrl(): ?string
    {
        return $this->previewItemId ? $this->getAdapter()->getUrl($this->previewItemId) : null;
    }

    public function getTextContent(): ?string
    {
        $item = $this->previewItem;
        if (!$item) return null;

        $fileType = $this->getFileTypeForItem($item);
        if ($fileType->identifier() !== 'text') return null;

        $maxSize = $fileType->metadata()['max_preview_size'] ?? 1024 * 1024;
        return $this->getAdapter()->getContents($this->previewItemId, $maxSize);
    }

    public function createFolder(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()->title('You are not authorized to create folders')->danger()->send();
            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:255']);

        $folderName = $this->newFolderName;
        $parentPath = $this->currentPath;

        $result = $this->getAdapter()->createFolder($folderName, $parentPath);
        if (is_string($result)) {
            Notification::make()->title($result)->danger()->send();
            return;
        }

        // Record the change
        $this->recordChange('created', 'folder', [
            'id' => $result->getIdentifier(),
            'name' => $folderName,
            'path' => $result->getPath(),
            'parent_path' => $parentPath,
        ]);

        $this->newFolderName = '';
        Notification::make()->title('Folder created successfully')->success()->send();
        $this->dispatch('close-modal', id: 'embedded-create-folder-modal-' . $this->getId());

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedItems)) return;

        if (!$this->getAuthorizationService()->canDeleteAny()) {
            Notification::make()->title('You are not authorized to delete items')->danger()->send();
            return;
        }

        // Capture item details before deletion
        $itemsToDelete = [];
        foreach ($this->selectedItems as $itemId) {
            $item = $this->getAdapter()->getItem($itemId);
            if ($item) {
                $itemsToDelete[] = [
                    'id' => $itemId,
                    'name' => $item->getName(),
                    'path' => $item->getPath(),
                    'item_type' => $item->isFolder() ? 'folder' : 'file',
                ];
            }
        }

        $count = $this->getAdapter()->deleteMany($this->selectedItems);

        // Record the changes
        foreach ($itemsToDelete as $itemInfo) {
            $this->recordChange('deleted', $itemInfo['item_type'], [
                'id' => $itemInfo['id'],
                'name' => $itemInfo['name'],
                'path' => $itemInfo['path'],
            ]);
        }

        $this->selectedItems = [];
        Notification::make()->title($count . ' item(s) deleted')->success()->send();

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    public function deleteItem(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);
        if (!$this->getAuthorizationService()->canDelete(null, $item)) {
            Notification::make()->title('You are not authorized to delete this item')->danger()->send();
            return;
        }

        // Capture item details before deletion
        $itemName = $item?->getName();
        $itemPath = $item?->getPath();
        $itemType = $item?->isFolder() ? 'folder' : 'file';

        $result = $this->getAdapter()->delete($itemId);
        if ($result === true) {
            // Record the change
            $this->recordChange('deleted', $itemType, [
                'id' => $itemId,
                'name' => $itemName,
                'path' => $itemPath,
            ]);

            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
            Notification::make()->title('Item deleted')->success()->send();

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()->title(is_string($result) ? $result : 'Failed to delete item')->danger()->send();
        }
    }

    public function openMoveDialog(string $itemId): void
    {
        $this->itemToMoveId = $itemId;
        $this->itemsToMove = [];
        $this->moveTargetPath = null;
        $this->dispatch('open-modal', id: 'embedded-move-modal-' . $this->getId());
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
        $this->dispatch('open-modal', id: 'embedded-move-modal-' . $this->getId());
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
                $this->recordChange('move', $itemId, ['to' => $this->moveTargetPath]);
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
        $this->dispatch('close-modal', id: 'embedded-move-modal-' . $this->getId());

        // Dispatch event to update other components (like panel sidebar)
        if ($successCount > 0) {
            $this->dispatch('filemanager-folder-changed');
        }
    }

    public function openCreateSubfolderDialog(string $parentPath): void
    {
        $this->subfolderParentPath = $parentPath;
        $this->subfolderName = '';
        $this->dispatch('open-modal', id: 'embedded-subfolder-modal-' . $this->getId());
    }

    public function createSubfolder(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()->title('You are not authorized to create folders')->danger()->send();
            return;
        }

        $this->validate(['subfolderName' => 'required|string|max:255']);

        $folderName = $this->subfolderName;
        $parentPath = $this->subfolderParentPath;

        $result = $this->getAdapter()->createFolder($folderName, $parentPath);
        if (is_string($result)) {
            Notification::make()->title($result)->danger()->send();
            return;
        }

        // Record the change
        $this->recordChange('created', 'folder', [
            'id' => $result->getIdentifier(),
            'name' => $folderName,
            'path' => $result->getPath(),
            'parent_path' => $parentPath,
        ]);

        if ($parentPath && !in_array($parentPath, $this->expandedFolders)) {
            $this->expandedFolders[] = $parentPath;
        }

        $this->subfolderName = '';
        $this->subfolderParentPath = null;
        Notification::make()->title('Subfolder created successfully')->success()->send();
        $this->dispatch('close-modal', id: 'embedded-subfolder-modal-' . $this->getId());

        // Dispatch event to update other components (like panel sidebar)
        $this->dispatch('filemanager-folder-changed');
    }

    public function openRenameDialog(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);
        if ($item) {
            $this->itemToRenameId = $itemId;
            $this->renameItemName = $item->getName();
            $this->dispatch('open-modal', id: 'embedded-rename-modal-' . $this->getId());
        }
    }

    public function renameItem(): void
    {
        if (!$this->itemToRenameId) return;

        $item = $this->getAdapter()->getItem($this->itemToRenameId);
        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()->title('You are not authorized to rename this item')->danger()->send();
            return;
        }

        $this->validate(['renameItemName' => 'required|string|max:255']);

        // Capture old name before rename
        $oldName = $item?->getName();
        $itemType = $item?->isFolder() ? 'folder' : 'file';
        $itemId = $this->itemToRenameId;
        $newName = $this->renameItemName;

        $result = $this->getAdapter()->rename($itemId, $newName);
        if ($result === true) {
            // Record the change
            $this->recordChange('renamed', $itemType, [
                'id' => $itemId,
                'old_name' => $oldName,
                'new_name' => $newName,
            ]);

            $this->itemToRenameId = null;
            $this->renameItemName = '';
            Notification::make()->title('Item renamed successfully')->success()->send();
            $this->dispatch('close-modal', id: 'embedded-rename-modal-' . $this->getId());

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()->title(is_string($result) ? $result : 'Failed to rename item')->danger()->send();
        }
    }

    public function uploadFiles(): void
    {
        if (!$this->getAuthorizationService()->canCreate()) {
            Notification::make()->title('You are not authorized to upload files')->danger()->send();
            return;
        }

        if (empty($this->uploadedFiles)) {
            Notification::make()->title('No files selected')->warning()->send();
            return;
        }

        $security = app(\MWGuerra\FileManager\Services\FileSecurityService::class);
        $uploadCount = 0;
        $errors = [];
        $parentPath = $this->currentPath;

        foreach ($this->uploadedFiles as $file) {
            $validation = $security->validateUpload($file);
            if (!$validation['valid']) {
                $errors[] = $file->getClientOriginalName() . ': ' . $validation['error'];
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $result = $this->getAdapter()->uploadFile($file, $parentPath);
            if (!is_string($result)) {
                $uploadCount++;

                // Record the change
                $this->recordChange('uploaded', 'file', [
                    'id' => $result->getIdentifier(),
                    'name' => $result->getName(),
                    'original_name' => $originalName,
                    'path' => $result->getPath(),
                    'parent_path' => $parentPath,
                    'size' => $result->getSize(),
                ]);
            } else {
                $errors[] = $originalName . ': ' . $result;
            }
        }

        $this->uploadedFiles = [];

        if ($uploadCount > 0) {
            Notification::make()->title($uploadCount . ' file(s) uploaded successfully')->success()->send();
        }
        if (!empty($errors)) {
            Notification::make()
                ->title('Some files could not be uploaded')
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->danger()
                ->send();
        }

        $this->dispatch('close-modal', id: 'embedded-upload-modal-' . $this->getId());
    }

    public function clearUploadedFiles(): void
    {
        $this->uploadedFiles = [];
    }

    public function setMoveTarget(?string $path): void
    {
        $this->moveTargetPath = $path;
    }

    public function moveItem(): void
    {
        if ($this->itemToMoveId === null) return;

        $item = $this->getAdapter()->getItem($this->itemToMoveId);
        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()->title('You are not authorized to move this item')->danger()->send();
            return;
        }

        // Capture details before move
        $itemId = $this->itemToMoveId;
        $itemName = $item?->getName();
        $itemType = $item?->isFolder() ? 'folder' : 'file';
        $fromPath = $item?->getParentPath();
        $toPath = $this->moveTargetPath;

        $result = $this->getAdapter()->move($itemId, $toPath);
        if ($result === true) {
            // Record the change
            $this->recordChange('moved', $itemType, [
                'id' => $itemId,
                'name' => $itemName,
                'from_path' => $fromPath,
                'to_path' => $toPath,
            ]);

            Notification::make()->title('Item moved successfully')->success()->send();
            $this->itemToMoveId = null;
            $this->moveTargetPath = null;
            $this->dispatch('close-modal', id: 'embedded-move-modal-' . $this->getId());

            // Dispatch event to update other components (like panel sidebar)
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()->title(is_string($result) ? $result : 'Failed to move item')->danger()->send();
        }
    }

    public function handleDrop(string $targetId, string $draggedId): void
    {
        $target = $this->getAdapter()->getItem($targetId);
        $dragged = $this->getAdapter()->getItem($draggedId);

        if (!$target || !$dragged || !$target->isFolder() || $targetId === $draggedId) return;

        $this->itemToMoveId = $draggedId;
        $this->moveTargetPath = $targetId;
        $this->moveItem();
    }

    public function getAllFoldersProperty(): Collection
    {
        return $this->getAdapter()->getFolders();
    }

    public function getItemToMoveProperty(): ?FileManagerItemInterface
    {
        return $this->itemToMoveId ? $this->getAdapter()->getItem($this->itemToMoveId) : null;
    }

    public function getSubfolderParentProperty(): ?FileManagerItemInterface
    {
        return $this->subfolderParentPath ? $this->getAdapter()->getItem($this->subfolderParentPath) : null;
    }

    public function getItemToRenameProperty(): ?FileManagerItemInterface
    {
        return $this->itemToRenameId ? $this->getAdapter()->getItem($this->itemToRenameId) : null;
    }

    public function getRootFileCountProperty(): int
    {
        $items = $this->getAdapter()->getItems(null);
        return $items->filter(fn ($item) => $item->isFile())->count();
    }

    /**
     * Record a change made in the file manager.
     *
     * @param string $type The type of change: created, renamed, deleted, moved, uploaded
     * @param string $itemType The type of item: folder, file
     * @param array $details Additional details about the change
     */
    protected function recordChange(string $type, string $itemType, array $details): void
    {
        if ($this->isReadOnly()) {
            return;
        }

        $this->changes[] = array_merge([
            'type' => $type,
            'item_type' => $itemType,
            'timestamp' => now()->toIso8601String(),
        ], $details);

        // Dispatch event so parent components can listen for changes
        $this->dispatch('filemanager-change', [
            'componentId' => $this->getId(),
            'change' => $this->changes[count($this->changes) - 1],
            'totalChanges' => count($this->changes),
        ]);
    }

    /**
     * Get all recorded changes.
     *
     * @return array
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Get the count of changes.
     *
     * @return int
     */
    public function getChangesCountProperty(): int
    {
        return count($this->changes);
    }

    /**
     * Clear all recorded changes.
     */
    public function clearChanges(): void
    {
        $this->changes = [];
        $this->dispatch('filemanager-changes-cleared', [
            'componentId' => $this->getId(),
        ]);
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

    public function render(): View
    {
        return view('filemanager::livewire.embedded-file-manager');
    }
}
