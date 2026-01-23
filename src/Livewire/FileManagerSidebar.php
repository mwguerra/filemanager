<?php

namespace MWGuerra\FileManager\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use MWGuerra\FileManager\Adapters\AdapterFactory;
use MWGuerra\FileManager\Contracts\FileManagerAdapterInterface;
use MWGuerra\FileManager\Contracts\FileManagerItemInterface;
use MWGuerra\FileManager\FileManagerPlugin;
use MWGuerra\FileManager\Services\AuthorizationService;

/**
 * File Manager Sidebar Livewire component.
 *
 * This component displays the folder tree structure as a sidebar
 * that can be rendered in the Filament panel navigation.
 */
class FileManagerSidebar extends Component
{
    /**
     * Currently selected folder path/ID.
     */
    public ?string $currentPath = null;

    /**
     * Array of expanded folder IDs.
     */
    public array $expandedFolders = [];

    /**
     * Cached folder tree with lazy-loaded children.
     * Keys are folder IDs, values are arrays of children.
     */
    public array $folderChildrenCache = [];

    /**
     * URL to navigate to when a folder is clicked.
     */
    public ?string $fileManagerUrl = null;

    // Subfolder creation properties
    public ?string $subfolderParentPath = null;
    public string $subfolderName = '';

    // Rename properties
    public ?string $itemToRenameId = null;
    public string $renameItemName = '';

    // Move properties
    public ?string $itemToMoveId = null;
    public ?string $moveTargetPath = null;

    public function mount(): void
    {
        $this->expandedFolders = ['root'];
        $this->fileManagerUrl = $this->getFileManagerUrl();
    }

    /**
     * Get the adapter based on mode.
     */
    protected function getAdapter(): FileManagerAdapterInterface
    {
        return AdapterFactory::make();
    }

    /**
     * Get the authorization service instance.
     */
    protected function getAuthorizationService(): AuthorizationService
    {
        return app(AuthorizationService::class);
    }

    /**
     * Check if the component is in read-only mode.
     */
    public function isReadOnly(): bool
    {
        return !$this->getAuthorizationService()->canCreate();
    }

    /**
     * Check if we should use lazy loading for the folder tree.
     *
     * Lazy loading is recommended for storage mode (S3, remote storage)
     * to avoid excessive API calls on page load.
     */
    protected function shouldUseLazyLoading(): bool
    {
        return $this->getAdapter()->getModeName() === 'storage';
    }

    /**
     * Get folder tree for the sidebar.
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
     * Get the root file count.
     */
    public function getRootFileCountProperty(): int
    {
        $items = $this->getAdapter()->getItems(null);

        return $items->filter(fn ($item) => $item->isFile())->count();
    }

    /**
     * Get all folders for move dialog.
     */
    public function getAllFoldersProperty(): Collection
    {
        return $this->getAdapter()->getFolders();
    }

    /**
     * Toggle folder expansion.
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
     * Navigate to folder - redirects to file manager page with folder selected.
     */
    public function navigateTo(?string $path): void
    {
        $this->currentPath = $path;

        $url = $this->fileManagerUrl;
        if ($path !== null) {
            $url .= '?path=' . urlencode($path);
        }

        $this->redirect($url);
    }

    /**
     * Listen for folder change events from the file manager page.
     */
    #[On('filemanager-folder-changed')]
    public function refreshFolderTree(): void
    {
        // Clear the children cache so fresh data is loaded
        $this->folderChildrenCache = [];
    }

    /**
     * Open create subfolder dialog.
     */
    public function openCreateSubfolderDialog(?string $parentPath): void
    {
        $this->subfolderParentPath = $parentPath;
        $this->subfolderName = '';
        $this->dispatch('open-modal', id: 'sidebar-create-subfolder-modal');
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

        // Expand the parent folder to show the new subfolder
        if ($this->subfolderParentPath && !in_array($this->subfolderParentPath, $this->expandedFolders)) {
            $this->expandedFolders[] = $this->subfolderParentPath;
        } elseif ($this->subfolderParentPath === null && !in_array('root', $this->expandedFolders)) {
            $this->expandedFolders[] = 'root';
        }

        $this->subfolderName = '';
        $this->subfolderParentPath = null;

        Notification::make()
            ->title('Folder created successfully')
            ->success()
            ->send();

        $this->dispatch('close-modal', id: 'sidebar-create-subfolder-modal');

        // Dispatch event to update other components
        $this->dispatch('filemanager-folder-changed');
    }

    /**
     * Open rename dialog for a folder.
     */
    public function openRenameDialog(string $itemId): void
    {
        $item = $this->getAdapter()->getItem($itemId);

        if ($item) {
            $this->itemToRenameId = $itemId;
            $this->renameItemName = $item->getName();
            $this->dispatch('open-modal', id: 'sidebar-rename-modal');
        }
    }

    /**
     * Rename a folder.
     */
    public function renameItem(): void
    {
        if (!$this->itemToRenameId) {
            return;
        }

        $item = $this->getAdapter()->getItem($this->itemToRenameId);

        if (!$item) {
            Notification::make()
                ->title('Folder not found')
                ->body('This folder may have been moved or deleted.')
                ->warning()
                ->send();
            $this->dispatch('close-modal', id: 'sidebar-rename-modal');
            return;
        }

        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()
                ->title('You are not authorized to rename this folder')
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
                ->title('Folder renamed successfully')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'sidebar-rename-modal');

            // Dispatch event to update other components
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()
                ->title(is_string($result) ? $result : 'Failed to rename folder')
                ->danger()
                ->send();
        }
    }

    /**
     * Open move dialog for a folder.
     */
    public function openMoveDialog(string $itemId): void
    {
        $this->itemToMoveId = $itemId;
        $this->moveTargetPath = null;
        $this->dispatch('open-modal', id: 'sidebar-move-modal');
    }

    /**
     * Set the move target path.
     */
    public function setMoveTarget(?string $path): void
    {
        $this->moveTargetPath = $path;
    }

    /**
     * Move a folder.
     */
    public function moveItem(): void
    {
        if (!$this->itemToMoveId) {
            return;
        }

        $item = $this->getAdapter()->getItem($this->itemToMoveId);

        if (!$item) {
            Notification::make()
                ->title('Folder not found')
                ->body('This folder may have been moved or deleted.')
                ->warning()
                ->send();
            $this->dispatch('close-modal', id: 'sidebar-move-modal');
            return;
        }

        if (!$this->getAuthorizationService()->canUpdate(null, $item)) {
            Notification::make()
                ->title('You are not authorized to move this folder')
                ->danger()
                ->send();
            return;
        }

        // Prevent moving folder into itself or its children
        if ($this->moveTargetPath === $this->itemToMoveId) {
            Notification::make()
                ->title('Cannot move folder into itself')
                ->danger()
                ->send();
            return;
        }

        $result = $this->getAdapter()->move($this->itemToMoveId, $this->moveTargetPath);

        if ($result === true) {
            Notification::make()
                ->title('Folder moved successfully')
                ->success()
                ->send();

            $this->itemToMoveId = null;
            $this->moveTargetPath = null;
            $this->dispatch('close-modal', id: 'sidebar-move-modal');

            // Dispatch event to update other components
            $this->dispatch('filemanager-folder-changed');
        } else {
            Notification::make()
                ->title(is_string($result) ? $result : 'Failed to move folder')
                ->danger()
                ->send();
        }
    }

    /**
     * Get the item to move.
     */
    public function getItemToMoveProperty(): ?FileManagerItemInterface
    {
        return $this->itemToMoveId ? $this->getAdapter()->getItem($this->itemToMoveId) : null;
    }

    /**
     * Get the subfolder parent.
     */
    public function getSubfolderParentProperty(): ?FileManagerItemInterface
    {
        return $this->subfolderParentPath ? $this->getAdapter()->getItem($this->subfolderParentPath) : null;
    }

    /**
     * Get the item to rename.
     */
    public function getItemToRenameProperty(): ?FileManagerItemInterface
    {
        return $this->itemToRenameId ? $this->getAdapter()->getItem($this->itemToRenameId) : null;
    }

    /**
     * Get the file manager page URL.
     */
    protected function getFileManagerUrl(): string
    {
        $mode = config('filemanager.mode', 'database');

        // Try to get the URL from route, fallback to a generic path if routes aren't registered
        try {
            if ($mode === 'storage') {
                return route('filament.admin.pages.file-system');
            }

            return route('filament.admin.pages.file-manager');
        } catch (\Exception $e) {
            // Fallback when routes aren't registered (e.g., in tests or when plugin isn't active)
            if ($mode === 'storage') {
                return '/admin/file-system';
            }

            return '/admin/file-manager';
        }
    }

    /**
     * Get the root folder label from plugin or config.
     */
    public function getRootLabelProperty(): string
    {
        return FileManagerPlugin::current()?->getPanelSidebarRootLabel()
            ?? config('filemanager.sidebar.root_label')
            ?? 'Root';
    }

    /**
     * Get sidebar heading from plugin or config.
     */
    public function getHeadingProperty(): string
    {
        return FileManagerPlugin::current()?->getPanelSidebarHeading()
            ?? config('filemanager.sidebar.heading')
            ?? 'Folders';
    }

    public function render(): View
    {
        return view('filemanager::livewire.file-manager-sidebar');
    }
}
