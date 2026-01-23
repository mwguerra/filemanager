{{-- File/Folder Row for Embedded List View --}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $itemId = $item->getIdentifier();
    $itemName = $item->getName();
    $isReadOnly = $isReadOnly ?? false;
    $isSelected = $this->isSelected($itemId);
@endphp
<div
    wire:key="list-{{ md5($itemId) }}"
    x-data="{ isDragOver: false }"
    @if(!$isReadOnly)
        draggable="true"
        x-on:dragstart="draggedItemId = @js($itemId); isDragging = true"
        x-on:dragend="isDragging = false; draggedItemId = null"
        @if($item->isFolder())
            x-on:dragover.prevent="isDragOver = true"
            x-on:dragleave="isDragOver = false"
            x-on:drop.prevent="isDragOver = false; if (draggedItemId && draggedItemId !== @js($itemId)) { $wire.handleDrop(@js($itemId), draggedItemId) }"
        @endif
    @endif
    x-on:click="$wire.handleItemClick(@js($itemId))"
    class="group flex cursor-pointer items-center gap-3 bg-white dark:bg-gray-900 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800
        {{ $isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}"
    @if(!$isReadOnly):class="{ 'bg-primary-50 dark:bg-primary-900/20': isDragOver }"@endif
>
    {{-- Selection Checkbox --}}
    @if(!$isReadOnly)
        <button
            type="button"
            x-on:click.stop="$wire.toggleSelection(@js($itemId), true)"
            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-all
                {{ $isSelected
                    ? 'border-primary-500 bg-primary-500 text-white'
                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}"
        >
            @if($isSelected)
                {!! FileManagerIcon::Check->render('w-2.5 h-2.5') !!}
            @endif
        </button>
    @endif

    {{-- Thumbnail/Icon --}}
    @if($item->isFolder())
        {!! FileManagerIcon::Folder->render('w-8 h-8 shrink-0 text-primary-500') !!}
    @else
        <div class="relative h-10 w-16 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-700">
            @if($item->getThumbnail())
                <img src="{{ $item->getThumbnail() }}" alt="{{ $itemName }}" class="h-full w-full object-cover" />
            @else
                <div class="flex h-full items-center justify-center">
                    @if($item->isVideo())
                        {!! FileManagerIcon::VideoCamera->render('w-5 h-5 text-red-500 dark:text-red-400') !!}
                    @elseif($item->isImage())
                        {!! FileManagerIcon::Photo->render('w-5 h-5 text-blue-500 dark:text-blue-400') !!}
                    @elseif($item->isDocument())
                        {!! FileManagerIcon::DocumentText->render('w-5 h-5 text-green-600 dark:text-green-400') !!}
                    @elseif($item->isAudio())
                        {!! FileManagerIcon::MusicalNote->render('w-5 h-5 text-purple-500 dark:text-purple-400') !!}
                    @else
                        {!! FileManagerIcon::Document->render('w-5 h-5 text-gray-500 dark:text-gray-400') !!}
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Name and Meta --}}
    <div class="flex flex-1 items-center justify-between gap-3 overflow-hidden">
        <div class="flex-1 overflow-hidden">
            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $itemName }}</p>
            @if($item->isFile())
                <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    @if($item->getDuration() && ($item->isVideo() || $item->isAudio()))
                        <span>{{ $item->getFormattedDuration() }}</span>
                    @endif
                    @if($item->getSize())
                        <span>{{ $item->getFormattedSize() }}</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if(!$isReadOnly)
                {{-- Dropdown Menu --}}
                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            x-on:click.stop
                            class="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            {!! FileManagerIcon::EllipsisVertical->render('w-4 h-4') !!}
                        </button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-arrow-right-circle"
                            x-on:click.stop="close(); $wire.openMoveDialog({{ json_encode($itemId) }})"
                        >
                            Move
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-pencil"
                            x-on:click.stop="close(); $wire.openRenameDialog({{ json_encode($itemId) }})"
                        >
                            Rename
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-trash"
                            color="danger"
                            x-on:click.stop="close(); if(confirm('Are you sure you want to delete this item?')) $wire.deleteItem({{ json_encode($itemId) }})"
                        >
                            Delete
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            @endif
        </div>
    </div>
</div>
