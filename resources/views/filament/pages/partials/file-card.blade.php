{{-- File/Folder Card for Grid View --}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $itemId = $item->getIdentifier();
    $itemName = $item->getName();
    $isReadOnly = $isReadOnly ?? false;
    $isSelected = $this->isSelected($itemId);
@endphp
<div
    wire:key="card-{{ md5($itemId) }}"
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
    class="group relative cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 transition-all hover:border-primary-500 hover:shadow-lg
        {{ $isSelected ? 'ring-2 ring-primary-500 border-primary-500 bg-primary-50 dark:bg-primary-900/20' : '' }}"
    @if(!$isReadOnly):class="{ 'border-primary-500 bg-primary-50 dark:bg-primary-900/20': isDragOver }"@endif
>
    {{-- Selection Checkbox --}}
    @if(!$isReadOnly)
        <button
            type="button"
            x-on:click.stop="$wire.toggleSelection(@js($itemId), true)"
            class="absolute left-2 top-2 z-10 flex h-5 w-5 items-center justify-center rounded border transition-all
                {{ $isSelected
                    ? 'border-primary-500 bg-primary-500 text-white'
                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 opacity-0 group-hover:opacity-100' }}"
        >
            @if($isSelected)
                {!! FileManagerIcon::Check->render('w-3 h-3') !!}
            @endif
        </button>
    @endif

    {{-- Thumbnail/Icon --}}
    <div class="flex flex-col gap-3">
        @if($item->isFolder())
            <div class="flex aspect-video items-center justify-center rounded-md bg-gray-100 dark:bg-gray-700">
                {!! FileManagerIcon::Folder->render('w-12 h-12 text-primary-500') !!}
            </div>
        @else
            <div class="relative aspect-video overflow-hidden rounded-md bg-gray-100 dark:bg-gray-700">
                @if($item->getThumbnail())
                    <img src="{{ $item->getThumbnail() }}" alt="{{ $itemName }}" class="h-full w-full object-cover" />
                @else
                    <div class="flex h-full items-center justify-center">
                        @if($item->isVideo())
                            {!! FileManagerIcon::VideoCamera->render('w-12 h-12 text-red-500 dark:text-red-400') !!}
                        @elseif($item->isImage())
                            {!! FileManagerIcon::Photo->render('w-12 h-12 text-blue-500 dark:text-blue-400') !!}
                        @elseif($item->isDocument())
                            {!! FileManagerIcon::DocumentText->render('w-12 h-12 text-green-600 dark:text-green-400') !!}
                        @elseif($item->isAudio())
                            {!! FileManagerIcon::MusicalNote->render('w-12 h-12 text-purple-500 dark:text-purple-400') !!}
                        @else
                            {!! FileManagerIcon::Document->render('w-12 h-12 text-gray-500 dark:text-gray-400') !!}
                        @endif
                    </div>
                @endif
                @if($item->isVideo())
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                        {!! FileManagerIcon::Play->render('w-8 h-8 text-white') !!}
                    </div>
                @endif
            </div>
        @endif

        {{-- Name and Meta --}}
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 overflow-hidden">
                <p class="truncate font-medium text-gray-900 dark:text-white">{{ $itemName }}</p>
                @if($item->isFile())
                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        @if($item->getDuration() && ($item->isVideo() || $item->isAudio()))
                            <span>{{ $item->getFormattedDuration() }}</span>
                        @endif
                        @if($item->getSize())
                            <span>{{ $item->getFormattedSize() }}</span>
                        @endif
                    </div>
                @endif
            </div>

            @if(!$isReadOnly)
                {{-- Dropdown Menu --}}
                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            x-on:click.stop
                            class="p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-gray-100 dark:hover:bg-gray-700 transition-opacity"
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
