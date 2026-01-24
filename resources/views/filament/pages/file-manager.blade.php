@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
@endphp
<x-filament-panels::page>
    @push('styles')
    <style>
        .fi-modal-close-overlay {
            width: 100vw !important;
        }
        html:has(.fi-modal-open) body {
            width: 100vw;
        }
    </style>
    @endpush

    <div class="flex flex-col h-[calc(100vh-12rem)] border border-gray-300 rounded-xl dark:border-none" x-data="{
        draggedItemId: null,
        isDragging: false,
    }">
        {{-- Header with Breadcrumbs and Controls --}}
        <div class="flex items-center justify-between border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 rounded-t-xl">
            {{-- Breadcrumbs --}}
            <nav class="flex items-center space-x-2 text-sm">
                @foreach($this->breadcrumbs as $index => $crumb)
                    @if($index > 0)
                        {!! FileManagerIcon::ChevronRight->render('w-4 h-4 text-gray-400') !!}
                    @endif
                    @if($index === count($this->breadcrumbs) - 1)
                        <span class="font-medium text-gray-900 dark:text-white">{{ $crumb['name'] }}</span>
                    @else
                        @php
                            $crumbId = $crumb['id'];
                        @endphp
                        <button
                            x-on:click="$wire.navigateTo({{ json_encode($crumbId) }})"
                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                        >
                            {{ $crumb['name'] }}
                        </button>
                    @endif
                @endforeach
            </nav>

            {{-- Controls --}}
            <div class="flex items-center gap-2">
                @if(!$this->isReadOnly())
                    {{-- New Folder Button --}}
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'create-folder-modal' })"
                        size="sm"
                        icon="heroicon-o-folder-plus"
                    >
                        New Folder
                    </x-filament::button>

                    {{-- Upload Button --}}
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'upload-files-modal' })"
                        size="sm"
                        icon="heroicon-o-arrow-up-tray"
                    >
                        Upload
                    </x-filament::button>
                @endif

                @if(count($selectedItems) > 0)
                    {{-- Clear Selection Button --}}
                    <x-filament::button
                        wire:click="clearSelection"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-x-mark"
                        title="Clear selection"
                    >
                        Clear ({{ count($selectedItems) }})
                    </x-filament::button>
                @endif

                {{-- Refresh Button --}}
                <x-filament::button
                    wire:click="refresh"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    title="Refresh"
                />

                {{-- View Mode Toggle --}}
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 p-1">
                    <button
                        wire:click="setViewMode('grid')"
                        class="p-1.5 rounded {{ $viewMode === 'grid' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="Grid view"
                    >
                        {!! FileManagerIcon::Squares2x2->render('w-4 h-4') !!}
                    </button>
                    <button
                        wire:click="setViewMode('list')"
                        class="p-1.5 rounded {{ $viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="List view"
                    >
                        {!! FileManagerIcon::ListBullet->render('w-4 h-4') !!}
                    </button>
                </div>
            </div>
        </div>

        {{-- Main Content Area --}}
        <div class="flex flex-1 overflow-hidden">
            {{-- Sidebar --}}
            @if($this->shouldShowPageSidebar())
            <aside class="w-64 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 overflow-y-auto">
                <h2 class="px-2 text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $this->getSidebarHeading() }}</h2>

                {{-- Root Folder --}}
                <nav class="space-y-1">
                    <div
                        x-data="{ showActions: false }"
                        @mouseenter="showActions = true"
                        @mouseleave="showActions = false"
                        class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === null ? 'font-medium' : '' }}"
                    >
                        {{-- Folder icon and name (clickable to navigate) --}}
                        <button
                            wire:click="navigateTo(null)"
                            class="flex items-center gap-2 flex-1 min-w-0 text-left"
                        >
                            {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $this->getSidebarRootLabel() }}</span>
                        </button>

                        {{-- Right side container for badge/actions (fixed width to prevent layout shift) --}}
                        @php $rootFileCount = $this->rootFileCount; @endphp
                        <div class="relative shrink-0 flex items-center justify-end" style="min-width: 72px;">
                            {{-- File count badge (shown when not hovered) --}}
                            @if($rootFileCount > 0)
                                <span
                                    class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                                    :class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"
                                >
                                    {{ $rootFileCount }}
                                </span>
                            @endif

                            @if(!$this->isReadOnly())
                                {{-- Hover actions (shown when hovered) --}}
                                <div
                                    class="flex items-center gap-0.5 transition-opacity duration-100"
                                    :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                >
                                    {{-- Add folder in root --}}
                                    <button
                                        x-on:click.stop="$dispatch('open-modal', { id: 'create-folder-modal' })"
                                        class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                        title="Add folder"
                                    >
                                        {!! FileManagerIcon::FolderPlus->render('w-3.5 h-3.5') !!}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Folder Tree (children of Root, indented) --}}
                    @include('filemanager::filament.pages.partials.folder-tree', ['folders' => $this->folderTree, 'level' => 1, 'currentPath' => $currentPath, 'isReadOnly' => $this->isReadOnly()])
                </nav>
            </aside>
            @endif

            {{-- Content Area --}}
            <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-900 p-6">
                @if($this->items->isEmpty())
                    {{-- Empty State --}}
                    <div class="flex flex-col items-center justify-center h-full">
                        {!! FileManagerIcon::FolderOpen->render('w-16 h-16 text-gray-400 dark:text-gray-500 mb-4') !!}
                        <p class="text-lg text-gray-600 dark:text-gray-400">This folder is empty</p>
                        <p class="text-sm text-gray-500 dark:text-gray-500">Create a new folder or upload files to get started</p>
                    </div>
                @else
                    {{-- Bulk Selection Management (only for non-read-only mode) --}}
                    @if(!$this->isReadOnly())
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 mb-4">
                            <div class="flex items-center gap-3">
                                {{-- Select All / Deselect All --}}
                                @if($this->allSelected())
                                    <button
                                        wire:click="clearSelection"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::XMark->render('w-4 h-4') !!}
                                        <span>Deselect All</span>
                                    </button>
                                @else
                                    <button
                                        wire:click="selectAll"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::CheckCircle->render('w-4 h-4') !!}
                                        <span>Select All</span>
                                    </button>
                                @endif

                                @if(count($selectedItems) > 0)
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ count($selectedItems) }} selected
                                    </span>

                                    <div class="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>

                                    {{-- Move Selected --}}
                                    <button
                                        wire:click="openMoveDialogForSelected"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::ArrowRightCircle->render('w-4 h-4') !!}
                                        <span>Move</span>
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                @if(count($selectedItems) > 0)
                                    {{-- Delete Selected --}}
                                    <button
                                        wire:click="deleteSelected"
                                        wire:confirm="Are you sure you want to delete {{ count($selectedItems) }} item(s)?"
                                        class="flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors"
                                    >
                                        {!! FileManagerIcon::Trash->render('w-4 h-4') !!}
                                        <span>Delete</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($viewMode === 'grid')
                        {{-- Grid View --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                            @foreach($this->items as $item)
                                @include('filemanager::filament.pages.partials.file-card', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @else
                        {{-- List View --}}
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->items as $item)
                                @include('filemanager::filament.pages.partials.file-list-item', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @endif
                @endif
            </main>
        </div>
    </div>

    {{-- Create Folder Modal --}}
    <x-filament::modal id="create-folder-modal" width="md">
        <x-slot name="heading">
            Create New Folder
        </x-slot>

        <x-slot name="description">
            Enter a name for your new folder
        </x-slot>

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
                x-on:click="$dispatch('close-modal', { id: 'create-folder-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button
                wire:click="createFolder"
            >
                Create Folder
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Move Item Modal --}}
    <x-filament::modal id="move-item-modal" width="md">
        <x-slot name="heading">
            @if(count($itemsToMove) > 0)
                Move {{ count($itemsToMove) }} Item(s)
            @else
                Move to Folder
            @endif
        </x-slot>

        <x-slot name="description">
            Select a destination folder
        </x-slot>

        <div class="max-h-96 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-2">
            {{-- Root option --}}
            <button
                wire:click="setMoveTarget(null)"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-800 {{ $moveTargetPath === null ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600' : '' }}"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                <span>Root</span>
            </button>

            {{-- Folder options --}}
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
                x-on:click="$dispatch('close-modal', { id: 'move-item-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            @if(count($itemsToMove) > 0)
                <x-filament::button
                    wire:click="moveSelected"
                >
                    Move {{ count($itemsToMove) }} Item(s)
                </x-filament::button>
            @else
                <x-filament::button
                    wire:click="moveItem"
                >
                    Move Here
                </x-filament::button>
            @endif
        </x-slot>
    </x-filament::modal>

    {{-- Create Subfolder Modal --}}
    <x-filament::modal id="create-subfolder-modal" width="md">
        <x-slot name="heading">
            Create Subfolder
        </x-slot>

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
                x-on:click="$dispatch('close-modal', { id: 'create-subfolder-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button
                wire:click="createSubfolder"
            >
                Create Subfolder
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Rename Item Modal --}}
    <x-filament::modal id="rename-item-modal" width="md">
        <x-slot name="heading">
            Rename Item
        </x-slot>

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
                x-on:click="$dispatch('close-modal', { id: 'rename-item-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button
                wire:click="renameItem"
            >
                Rename
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Upload Files Modal --}}
    <x-filament::modal id="upload-files-modal" width="lg">
        <x-slot name="heading">
            Upload Files
        </x-slot>

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
                class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors"
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
                <div class="space-y-2" wire:loading.remove wire:target="uploadedFiles, uploadedFile">
                    {!! FileManagerIcon::CloudArrowUp->render('w-12 h-12 mx-auto text-gray-400') !!}
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-primary-600 dark:text-primary-400">Click to upload</span>
                        or drag and drop
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        @if($this->supportsMultipleUploads())
                            Any file type supported
                        @else
                            Upload one file at a time (S3 temp storage limitation)
                        @endif
                    </p>
                </div>
                <div class="space-y-2" wire:loading wire:target="uploadedFiles, uploadedFile">
                    <div class="w-12 h-12 mx-auto border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        Processing files...
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        Please wait while files are being prepared
                    </p>
                </div>
            </div>

            {{-- Selected files preview --}}
            @if(count($uploadedFiles) > 0)
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ count($uploadedFiles) }} file(s) ready to upload:
                    </p>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 max-h-32 overflow-y-auto">
                        @foreach($uploadedFiles as $file)
                            <li class="flex items-center gap-2">
                                {!! FileManagerIcon::CheckCircle->render('w-4 h-4 shrink-0 text-success-500') !!}
                                <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                <span class="text-xs text-gray-400">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'upload-files-modal' })"
                wire:click="clearUploadedFiles"
                color="gray"
            >
                Cancel
            </x-filament::button>
            <x-filament::button
                wire:click="uploadFiles"
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

    {{-- Preview Modal --}}
    <x-filament::modal id="preview-modal" width="5xl" :close-by-clicking-away="true">
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
                            class="w-6 h-6 {{ $fileType->iconColor() }}"
                        />
                    @else
                        {!! FileManagerIcon::Document->render('w-6 h-6 text-gray-400') !!}
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
                    @if($previewItem->getDuration())
                        <span>{{ $previewItem->getFormattedDuration() }}</span>
                    @endif
                </div>
            </x-slot>

            <div class="min-h-[300px] max-h-[70vh] overflow-auto">
                @if($viewerComponent && $previewUrl)
                    {{-- Use dynamic viewer component from FileType --}}
                    @if($fileType->identifier() === 'text' && $textContent !== null)
                        @include($viewerComponent, ['content' => $textContent, 'url' => $previewUrl, 'item' => $previewItem])
                    @else
                        @include($viewerComponent, ['url' => $previewUrl, 'item' => $previewItem, 'fileType' => $fileType])
                    @endif
                @elseif($fileType && !$fileType->canPreview())
                    {{-- No Preview Available - use fallback --}}
                    @include('filemanager::components.viewers.fallback', [
                        'url' => $previewUrl,
                        'item' => $previewItem,
                        'fileType' => $fileType
                    ])
                @else
                    {{-- Fallback for unknown types --}}
                    @include('filemanager::components.viewers.fallback', [
                        'url' => $previewUrl,
                        'item' => $previewItem,
                        'fileType' => $fileType
                    ])
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
                                icon="heroicon-o-arrow-down-tray"
                            >
                                Download
                            </x-filament::button>
                        @endif
                        @if(!$this->isReadOnly())
                            <x-filament::button
                                x-on:click="$wire.openMoveDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                icon="heroicon-o-arrow-right-circle"
                            >
                                Move
                            </x-filament::button>
                            <x-filament::button
                                x-on:click="$wire.openRenameDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                icon="heroicon-o-pencil"
                            >
                                Rename
                            </x-filament::button>
                        @endif
                    </div>
                    <x-filament::button
                        x-on:click="$dispatch('close-modal', { id: 'preview-modal' })"
                    >
                        Close
                    </x-filament::button>
                </div>
            </x-slot>
        @endif
    </x-filament::modal>
</x-filament-panels::page>
