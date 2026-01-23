{{-- Recursive folder tree component --}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $isReadOnly = $isReadOnly ?? false;
@endphp
@foreach($folders as $folder)
    @php
        $folderId = (string) $folder['id'];
    @endphp
    <div>
        <div
            x-data="{ showActions: false }"
            @mouseenter="showActions = true"
            @mouseleave="showActions = false"
            class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === $folderId ? 'font-medium' : '' }}"
            style="padding-left: {{ (($level - 1) * 12) + 4 }}px"
        >
            {{-- Chevron toggle --}}
            @if(count($folder['children']) > 0)
                <button
                    x-on:click.stop="$wire.toggleFolder({{ json_encode($folderId) }})"
                    class="flex items-center justify-center w-5 h-5 rounded hover:bg-gray-300 dark:hover:bg-gray-600 shrink-0"
                    title="Expand/collapse"
                >
                    @if($this->isFolderExpanded($folderId))
                        {!! FileManagerIcon::ChevronDown->render('w-3.5 h-3.5') !!}
                    @else
                        {!! FileManagerIcon::ChevronRight->render('w-3.5 h-3.5') !!}
                    @endif
                </button>
            @else
                <span class="w-5 shrink-0"></span>
            @endif

            {{-- Folder icon and name (clickable to navigate) --}}
            <button
                x-on:click="$wire.navigateTo({{ json_encode($folderId) }})"
                class="flex items-center gap-2 flex-1 min-w-0 text-left"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                <span class="truncate text-gray-700 dark:text-gray-300">{{ $folder['name'] }}</span>
            </button>

            {{-- Right side container for badge/actions (fixed width to prevent layout shift) --}}
            <div class="relative shrink-0 flex items-center justify-end" style="min-width: {{ $isReadOnly ? '32px' : '72px' }};">
                {{-- File count badge (shown when not hovered or always in read-only mode) --}}
                @if($folder['file_count'] > 0)
                    <span
                        class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                        @if(!$isReadOnly):class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"@endif
                    >
                        {{ $folder['file_count'] }}
                    </span>
                @endif

                @if(!$isReadOnly)
                    {{-- Hover actions (shown when hovered) --}}
                    <div
                        class="flex items-center gap-0.5 transition-opacity duration-100"
                        :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                    >
                        {{-- Add subfolder --}}
                        <button
                            x-on:click.stop="$wire.openCreateSubfolderDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="Add subfolder"
                        >
                            {!! FileManagerIcon::FolderPlus->render('w-3.5 h-3.5') !!}
                        </button>

                        {{-- Rename --}}
                        <button
                            x-on:click.stop="$wire.openRenameDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="Rename"
                        >
                            {!! FileManagerIcon::Pencil->render('w-3.5 h-3.5') !!}
                        </button>

                        {{-- Move --}}
                        <button
                            x-on:click.stop="$wire.openMoveDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="Move"
                        >
                            {!! FileManagerIcon::ArrowRightCircleMini->render('w-3.5 h-3.5') !!}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Children folders --}}
        @if(count($folder['children']) > 0 && $this->isFolderExpanded($folderId))
            @include('filemanager::filament.pages.partials.folder-tree', ['folders' => $folder['children'], 'level' => $level + 1, 'isReadOnly' => $isReadOnly])
        @endif
    </div>
@endforeach
