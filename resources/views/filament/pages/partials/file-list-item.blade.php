{{-- File/Folder Row for List View --}}
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
    class="group flex cursor-pointer items-center gap-4 bg-white dark:bg-gray-900 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800
        {{ $isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}"
    @if(!$isReadOnly):class="{ 'bg-primary-50 dark:bg-primary-900/20': isDragOver }"@endif
>
    {{-- Selection Checkbox --}}
    @if(!$isReadOnly)
        <button
            type="button"
            x-on:click.stop="$wire.toggleSelection(@js($itemId), true)"
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border transition-all
                {{ $isSelected
                    ? 'border-primary-500 bg-primary-500 text-white'
                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}"
        >
            @if($isSelected)
                {!! FileManagerIcon::Check->render('w-3 h-3') !!}
            @endif
        </button>
    @endif

    {{-- Thumbnail/Icon --}}
    @if($item->isFolder())
        {!! FileManagerIcon::Folder->render('w-10 h-10 shrink-0 text-primary-500') !!}
    @else
        <div class="relative h-14 w-24 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-700">
            @if($item->getThumbnail())
                <img src="{{ $item->getThumbnail() }}" alt="{{ $itemName }}" class="h-full w-full object-cover" />
            @else
                <div class="flex h-full items-center justify-center">
                    @if($item->isVideo())
                        {!! FileManagerIcon::VideoCamera->render('w-6 h-6 text-red-500 dark:text-red-400') !!}
                    @elseif($item->isImage())
                        {!! FileManagerIcon::Photo->render('w-6 h-6 text-blue-500 dark:text-blue-400') !!}
                    @elseif($item->isDocument())
                        {!! FileManagerIcon::DocumentText->render('w-6 h-6 text-green-600 dark:text-green-400') !!}
                    @elseif($item->isAudio())
                        {!! FileManagerIcon::MusicalNote->render('w-6 h-6 text-purple-500 dark:text-purple-400') !!}
                    @else
                        {!! FileManagerIcon::Document->render('w-6 h-6 text-gray-500 dark:text-gray-400') !!}
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Name and Meta --}}
    <div class="flex flex-1 items-center justify-between gap-4 overflow-hidden">
        <div class="flex-1 overflow-hidden">
            <p class="truncate font-medium text-gray-900 dark:text-white">{{ $itemName }}</p>
            @if($item->isFile())
                <div class="mt-1 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
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
                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            {!! FileManagerIcon::EllipsisVertical->render('w-5 h-5') !!}
                        </button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-arrow-right-circle"
                            x-on:click.stop="close"
                            wire:click="openMoveDialog('{{ $itemId }}')"
                        >
                            Move
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-pencil"
                            x-on:click.stop="close"
                            wire:click="openRenameDialog('{{ $itemId }}')"
                        >
                            Rename
                        </x-filament::dropdown.list.item>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-trash"
                            color="danger"
                            x-on:click.stop="close"
                            wire:click="deleteItem('{{ $itemId }}')"
                            wire:confirm="Are you sure you want to delete this item?"
                        >
                            Delete
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            @endif
        </div>
    </div>
</div>
