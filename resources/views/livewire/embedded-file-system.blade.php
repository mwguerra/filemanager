{{-- EmbeddedFileSystem - Read-only file browser --}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
@endphp
<div class="fi-embedded-file-manager border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden" style="height: {{ $height }};">
    <div class="flex flex-col h-full" x-data="{}">
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
                    <button
                        x-on:click="$wire.navigateTo(null)"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === null ? 'font-medium' : '' }}"
                    >
                        {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                        <span class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $sidebarRootLabel }}</span>
                    </button>

                    {{-- Folder Tree --}}
                    @include('filemanager::livewire.partials.embedded-folder-tree', ['folders' => $this->folderTree, 'level' => 1, 'currentPath' => $currentPath, 'isReadOnly' => true])
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
                    </div>
                @else
                    @if($viewMode === 'grid')
                        {{-- Grid View --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            @foreach($this->items as $item)
                                @include('filemanager::livewire.partials.embedded-file-card', ['item' => $item, 'isReadOnly' => true])
                            @endforeach
                        </div>
                    @else
                        {{-- List View --}}
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->items as $item)
                                @include('filemanager::livewire.partials.embedded-file-list-item', ['item' => $item, 'isReadOnly' => true])
                            @endforeach
                        </div>
                    @endif
                @endif
            </main>
        </div>
    </div>

    {{-- Preview Modal (read-only version) --}}
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
