{{--
Fallback Viewer Component

Displayed when no specific viewer is available for the file type.

Variables:
- $url: The URL of the file (optional)
- $item: The FileSystemItem model (optional)
- $fileType: The FileTypeContract instance (optional)
--}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    $fileType = $fileType ?? null;
@endphp

<div class="flex flex-col items-center justify-center p-12 text-gray-500 dark:text-gray-400">
    @if($fileType)
        <x-dynamic-component
            :component="$fileType->icon()"
            class="w-20 h-20 mb-4 {{ $fileType->iconColor() }}"
        />
    @else
        {!! FileManagerIcon::EyeSlash->render('w-20 h-20 mb-4') !!}
    @endif

    <p class="text-lg font-medium text-gray-700 dark:text-gray-300">
        Preview not available
    </p>
    <p class="text-sm mt-1 text-center max-w-md text-gray-600 dark:text-gray-400">
        This file type cannot be previewed in the browser.
        @if($fileType)
            <br><span class="text-gray-500 dark:text-gray-400">Type: {{ $fileType->label() }}</span>
        @endif
    </p>

    @if($url)
        <a
            href="{{ $url }}"
            download="{{ $item?->name }}"
            class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors"
        >
            {!! FileManagerIcon::ArrowDownTray->render('w-5 h-5') !!}
            Download file
        </a>
    @endif

    @if($item)
        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            @if($item->size)
                <span>{{ $item->getFormattedSize() }}</span>
            @endif
        </div>
    @endif
</div>
