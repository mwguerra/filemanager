@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
@endphp
<div class="fi-embedded-file-manager border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden" style="height: {{ $height }};">
    @once
    <style>
        .fi-modal-close-overlay {
            width: 100vw !important;
        }
        html:has(.fi-modal-open) body {
            width: 100vw;
        }
    </style>
    @endonce
    <div class="flex flex-col h-full" x-data="{
        draggedItemId: null,
        isDragging: false,
    }">
        @if($showHeader)
        {{-- Header with Breadcrumbs and Controls --}}
        <div class="flex items-center justify-between border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3">
            {{-- Breadcrumbs --}}
            <nav class="flex items-center space-x-2 text-sm">
                @foreach($this->breadcrumbs as $index => $crumb)
                    @if($index > 0)
                        {!! FileManagerIcon::ChevronRight->render('w-4 h-4 text-gray-400') !!}
                    @endif
                    @php
                        $crumbName = $index === 0 ? $breadcrumbsRootLabel : $crumb['name'];
                        $crumbId = $crumb['id'];
                    @endphp
                    @if($index === count($this->breadcrumbs) - 1)
                        <span class="font-medium text-gray-900 dark:text-white">{{ $crumbName }}</span>
                    @else
                        <button
                            x-on:click="$wire.navigateTo({{ json_encode($crumbId) }})"
                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                        >
                            {{ $crumbName }}
                        </button>
                    @endif
                @endforeach
            </nav>

            {{-- Controls --}}
            <div class="flex items-center gap-2">
                @if(!$this->isReadOnly())
                    {{-- New Folder Button --}}
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'embedded-create-folder-modal-{{ $this->getId() }}' })"
                        size="xs"
                        icon="heroicon-o-folder-plus"
                    >
                        New Folder
                    </x-filament::button>

                    {{-- Upload Button --}}
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'embedded-upload-modal-{{ $this->getId() }}' })"
                        size="xs"
                        icon="heroicon-o-arrow-up-tray"
                    >
                        Upload
                    </x-filament::button>
                @endif

                @if(count($selectedItems) > 0)
                    {{-- Clear Selection Button --}}
                    <x-filament::button
                        x-on:click="$wire.clearSelection()"
                        size="xs"
                        color="gray"
                        icon="heroicon-o-x-mark"
                        title="Clear selection"
                    >
                        Clear ({{ count($selectedItems) }})
                    </x-filament::button>
                @endif

                {{-- Refresh Button --}}
                <x-filament::button
                    x-on:click="$wire.refresh()"
                    size="xs"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    title="Refresh"
                />

                {{-- View Mode Toggle --}}
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 p-0.5">
                    <button
                        x-on:click="$wire.setViewMode('grid')"
                        class="p-1 rounded {{ $viewMode === 'grid' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="Grid view"
                    >
                        {!! FileManagerIcon::Squares2x2->render('w-3.5 h-3.5') !!}
                    </button>
                    <button
                        x-on:click="$wire.setViewMode('list')"
                        class="p-1 rounded {{ $viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="List view"
                    >
                        {!! FileManagerIcon::ListBullet->render('w-3.5 h-3.5') !!}
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- Main Content Area --}}
        <div class="flex flex-1 overflow-hidden">
            @if($showSidebar)
            {{-- Sidebar --}}
            <aside class="w-56 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 overflow-y-auto">
                <h2 class="px-2 text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ $sidebarHeading }}</h2>

                {{-- Root Folder --}}
                <nav class="space-y-0.5">
                    <div
                        x-data="{ showActions: false }"
                        @mouseenter="showActions = true"
                        @mouseleave="showActions = false"
                        class="flex w-full items-center gap-1 rounded-md px-2 py-1 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === null ? 'font-medium' : '' }}"
                    >
                        <button
                            x-on:click="$wire.navigateTo(null)"
                            class="flex items-center gap-2 flex-1 min-w-0 text-left"
                        >
                            {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                            <span class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $sidebarRootLabel }}</span>
                        </button>

                        @php $rootFileCount = $this->rootFileCount; @endphp
                        <div class="relative shrink-0 flex items-center justify-end" style="min-width: {{ $this->isReadOnly() ? '32px' : '60px' }};">
                            @if($rootFileCount > 0)
                                <span
                                    class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                                    @if(!$this->isReadOnly()):class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"@endif
                                >
                                    {{ $rootFileCount }}
                                </span>
                            @endif

                            @if(!$this->isReadOnly())
                                <div
                                    class="flex items-center gap-0.5 transition-opacity duration-100"
                                    :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                >
                                    <button
                                        x-on:click.stop="$dispatch('open-modal', { id: 'embedded-create-folder-modal-{{ $this->getId() }}' })"
                                        class="p-0.5 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                        title="Add folder"
                                    >
                                        {!! FileManagerIcon::FolderPlus->render('w-3 h-3') !!}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Folder Tree --}}
                    @include('filemanager::livewire.partials.embedded-folder-tree', ['folders' => $this->folderTree, 'level' => 1, 'currentPath' => $currentPath, 'isReadOnly' => $this->isReadOnly()])
                </nav>
            </aside>
            @endif

            {{-- Content Area --}}
            <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-900 p-4">
                @if($this->items->isEmpty())
                    {{-- Empty State --}}
                    <div class="flex flex-col items-center justify-center h-full">
                        {!! FileManagerIcon::FolderOpen->render('w-12 h-12 text-gray-400 dark:text-gray-500 mb-3') !!}
                        <p class="text-sm text-gray-600 dark:text-gray-400">This folder is empty</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Create a folder or upload files to get started</p>
                    </div>
                @else
                    {{-- Bulk Selection Management (only for non-read-only mode) --}}
                    @if(!$this->isReadOnly())
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 mb-3">
                            <div class="flex items-center gap-2">
                                {{-- Select All / Deselect All --}}
                                @if($this->allSelected())
                                    <button
                                        x-on:click="$wire.clearSelection()"
                                        class="flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::XMark->render('w-3.5 h-3.5') !!}
                                        <span>Deselect All</span>
                                    </button>
                                @else
                                    <button
                                        x-on:click="$wire.selectAll()"
                                        class="flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::CheckCircle->render('w-3.5 h-3.5') !!}
                                        <span>Select All</span>
                                    </button>
                                @endif

                                @if(count($selectedItems) > 0)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ count($selectedItems) }} selected
                                    </span>

                                    <div class="h-3 w-px bg-gray-300 dark:bg-gray-600"></div>

                                    {{-- Move Selected --}}
                                    <button
                                        x-on:click="$wire.openMoveDialogForSelected()"
                                        class="flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::ArrowRightCircle->render('w-3.5 h-3.5') !!}
                                        <span>Move</span>
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if(count($selectedItems) > 0)
                                    {{-- Delete Selected --}}
                                    <button
                                        x-on:click="if(confirm('Are you sure you want to delete {{ count($selectedItems) }} item(s)?')) $wire.deleteSelected()"
                                        class="flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors"
                                    >
                                        {!! FileManagerIcon::Trash->render('w-3.5 h-3.5') !!}
                                        <span>Delete</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($viewMode === 'grid')
                        {{-- Grid View --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            @foreach($this->items as $item)
                                @include('filemanager::livewire.partials.embedded-file-card', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @else
                        {{-- List View --}}
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->items as $item)
                                @include('filemanager::livewire.partials.embedded-file-list-item', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @endif
                @endif
            </main>
        </div>
    </div>

    {{-- Write-operation modals (only render for non-read-only mode) --}}
    @if(!$this->isReadOnly())
    {{-- Create Folder Modal --}}
    <x-filament::modal id="embedded-create-folder-modal-{{ $this->getId() }}" width="md">
        <x-slot name="heading">Create New Folder</x-slot>
        <x-slot name="description">Enter a name for your new folder</x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live="newFolderName"
                    placeholder="My Videos"
                    wire:keydown.enter="createFolder"
                    autofocus
                />
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'embedded-create-folder-modal-{{ $this->getId() }}' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button x-on:click="$wire.createFolder()">
                Create Folder
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Move Item Modal --}}
    <x-filament::modal id="embedded-move-modal-{{ $this->getId() }}" width="md">
        <x-slot name="heading">
            @if(count($itemsToMove) > 0)
                Move {{ count($itemsToMove) }} Item(s)
            @else
                Move to Folder
            @endif
        </x-slot>
        <x-slot name="description">Select a destination folder</x-slot>

        <div class="max-h-64 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-2">
            <button
                x-on:click="$wire.setMoveTarget(null)"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-800 {{ $moveTargetPath === null ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600' : '' }}"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                <span>Root</span>
            </button>

            @foreach($this->allFolders as $folder)
                @php
                    $folderId = $folder->getIdentifier();
                    $itemBeingMoved = $this->itemToMove;
                    $isCurrentFolder = $itemBeingMoved && $itemBeingMoved->getParentPath() === $folder->getPath();
                    $isSameItem = $itemToMoveId === $folderId;
                    $isBulkMove = count($itemsToMove) > 0;
                    $isDisabled = $isBulkMove ? in_array($folderId, $itemsToMove) : ($isCurrentFolder || $isSameItem);
                @endphp
                <button
                    x-on:click="$wire.setMoveTarget({{ json_encode($folderId) }})"
                    @if($isDisabled) disabled @endif
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors
                        {{ $moveTargetPath === $folderId ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}
                        {{ $isDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                    style="padding-left: {{ $folder->getDepth() * 16 + 12 }}px"
                >
                    {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                    <span>{{ $folder->getName() }}</span>
                </button>
            @endforeach
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'embedded-move-modal-{{ $this->getId() }}' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            @if(count($itemsToMove) > 0)
                <x-filament::button x-on:click="$wire.moveSelected()">
                    Move {{ count($itemsToMove) }} Item(s)
                </x-filament::button>
            @else
                <x-filament::button x-on:click="$wire.moveItem()">
                    Move Here
                </x-filament::button>
            @endif
        </x-slot>
    </x-filament::modal>

    {{-- Create Subfolder Modal --}}
    <x-filament::modal id="embedded-subfolder-modal-{{ $this->getId() }}" width="md">
        <x-slot name="heading">Create Subfolder</x-slot>
        <x-slot name="description">
            @if($this->subfolderParent)
                Create a new folder inside "{{ $this->subfolderParent->getName() }}"
            @else
                Enter a name for your new subfolder
            @endif
        </x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live="subfolderName"
                    placeholder="New Folder"
                    wire:keydown.enter="createSubfolder"
                    autofocus
                />
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'embedded-subfolder-modal-{{ $this->getId() }}' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button x-on:click="$wire.createSubfolder()">
                Create Subfolder
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Rename Item Modal --}}
    <x-filament::modal id="embedded-rename-modal-{{ $this->getId() }}" width="md">
        <x-slot name="heading">Rename Item</x-slot>
        <x-slot name="description">
            @if($this->itemToRename)
                Rename "{{ $this->itemToRename->getName() }}"
            @else
                Enter a new name for this item
            @endif
        </x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live="renameItemName"
                    placeholder="New name"
                    wire:keydown.enter="renameItem"
                    autofocus
                />
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'embedded-rename-modal-{{ $this->getId() }}' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button x-on:click="$wire.renameItem()">
                Rename
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Upload Files Modal --}}
    <x-filament::modal id="embedded-upload-modal-{{ $this->getId() }}" width="lg">
        <x-slot name="heading">Upload Files</x-slot>
        <x-slot name="description">
            @php
                $maxSizeMB = round(config('filemanager.upload.max_file_size', 102400) / 1024, 0);
            @endphp
            @if($this->supportsMultipleUploads())
                Select one or more files to upload (max {{ $maxSizeMB }}MB per file)
            @else
                Select a file to upload (max {{ $maxSizeMB }}MB)
            @endif
        </x-slot>

        <div class="space-y-4">
            <div
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                class="relative border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                :class="isDragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'"
            >
                <input
                    type="file"
                    x-ref="fileInput"
                    @if($this->supportsMultipleUploads())
                        wire:model.live="uploadedFiles"
                        multiple
                    @else
                        wire:model.live="uploadedFile"
                    @endif
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                />
                <div class="space-y-2" wire:loading.remove wire:target="uploadedFiles">
                    {!! FileManagerIcon::CloudArrowUp->render('w-10 h-10 mx-auto text-gray-400 dark:text-gray-500') !!}
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-primary-600 dark:text-primary-400">Click to upload</span>
                        or drag and drop
                    </p>
                    @if(!$this->supportsMultipleUploads())
                        <p class="text-xs text-warning-600 dark:text-warning-400">
                            One file at a time (S3 storage limitation)
                        </p>
                    @endif
                </div>
                <div class="space-y-2" wire:loading wire:target="uploadedFiles">
                    <div class="w-10 h-10 mx-auto border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Processing files...</p>
                </div>
            </div>

            @if(count($uploadedFiles) > 0)
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ count($uploadedFiles) }} file(s) ready to upload:
                    </p>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 max-h-24 overflow-y-auto">
                        @foreach($uploadedFiles as $file)
                            <li class="flex items-center gap-2">
                                {!! FileManagerIcon::CheckCircle->render('w-4 h-4 shrink-0 text-success-500') !!}
                                <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$wire.clearUploadedFiles(); $dispatch('close-modal', { id: 'embedded-upload-modal-{{ $this->getId() }}' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button
                x-on:click="$wire.uploadFiles()"
                wire:loading.attr="disabled"
                wire:target="uploadedFiles, uploadFiles"
                :disabled="count($uploadedFiles) === 0"
            >
                <span wire:loading.remove wire:target="uploadedFiles, uploadFiles">
                    @if(count($uploadedFiles) > 0)
                        Upload {{ count($uploadedFiles) }} File(s)
                    @else
                        Select Files First
                    @endif
                </span>
                <span wire:loading wire:target="uploadedFiles">Processing...</span>
                <span wire:loading wire:target="uploadFiles">Uploading...</span>
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
    @endif

    {{-- Preview Modal --}}
    <x-filament::modal id="embedded-preview-modal-{{ $this->getId() }}" width="5xl" :close-by-clicking-away="true">
        @if($this->previewItem)
            @php
                $previewItem = $this->previewItem;
                $fileType = $this->previewFileType;
                $previewUrl = $this->getPreviewUrl();
                $textContent = $this->getTextContent();
                $viewerComponent = $fileType?->viewerComponent();
            @endphp

            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    @if($fileType)
                        <x-dynamic-component
                            :component="$fileType->icon()"
                            class="w-5 h-5 {{ $fileType->iconColor() }}"
                        />
                    @else
                        {!! FileManagerIcon::Document->render('w-5 h-5 text-gray-500 dark:text-gray-400') !!}
                    @endif
                    <span class="truncate">{{ $previewItem->getName() }}</span>
                </div>
            </x-slot>

            <x-slot name="description">
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>{{ $previewItem->getPath() }}</span>
                    @if($fileType)
                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs">{{ $fileType->label() }}</span>
                    @endif
                    @if($previewItem->getSize())
                        <span>{{ $previewItem->getFormattedSize() }}</span>
                    @endif
                </div>
            </x-slot>

            <div class="min-h-[200px] max-h-[60vh] overflow-auto">
                @if($viewerComponent && $previewUrl)
                    @if($fileType->identifier() === 'text' && $textContent !== null)
                        @include($viewerComponent, ['content' => $textContent, 'url' => $previewUrl, 'item' => $previewItem])
                    @else
                        @include($viewerComponent, ['url' => $previewUrl, 'item' => $previewItem, 'fileType' => $fileType])
                    @endif
                @elseif($fileType && !$fileType->canPreview())
                    @include('filemanager::components.viewers.fallback', ['url' => $previewUrl, 'item' => $previewItem, 'fileType' => $fileType])
                @else
                    @include('filemanager::components.viewers.fallback', ['url' => $previewUrl, 'item' => $previewItem, 'fileType' => $fileType])
                @endif
            </div>

            <x-slot name="footerActions">
                <div class="flex w-full justify-between">
                    <div class="flex gap-2">
                        @if($previewUrl)
                            <x-filament::button
                                tag="a"
                                href="{{ $previewUrl }}"
                                target="_blank"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-arrow-down-tray"
                            >
                                Download
                            </x-filament::button>
                        @endif
                        @if(!$this->isReadOnly())
                            <x-filament::button
                                x-on:click="$wire.openMoveDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-arrow-right-circle"
                            >
                                Move
                            </x-filament::button>
                            <x-filament::button
                                x-on:click="$wire.openRenameDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-pencil"
                            >
                                Rename
                            </x-filament::button>
                        @endif
                    </div>
                    <x-filament::button
                        x-on:click="$dispatch('close-modal', { id: 'embedded-preview-modal-{{ $this->getId() }}' })"
                    >
                        Close
                    </x-filament::button>
                </div>
            </x-slot>
        @endif
    </x-filament::modal>
</div>
