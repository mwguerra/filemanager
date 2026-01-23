{{--
PDF Viewer Component with error handling

Variables:
- $url: The URL of the PDF file
- $item: The FileSystemItem model (optional)
--}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    // Create a unique key to force Alpine re-initialization when URL changes
    $viewerKey = 'pdf-' . md5($url ?? '');
@endphp

<div
    wire:key="{{ $viewerKey }}"
    x-data="{
        loaded: false,
        error: false,
        loading: true,
        timeout: null,
        init() {
            // Set a timeout for loading - if iframe doesn't load in 10 seconds, show error
            this.timeout = setTimeout(() => {
                if (!this.loaded) {
                    this.error = true;
                    this.loading = false;
                }
            }, 10000);
        },
        handleLoad() {
            if (this.timeout) clearTimeout(this.timeout);
            this.loaded = true;
            this.loading = false;
        },
        handleError() {
            if (this.timeout) clearTimeout(this.timeout);
            this.error = true;
            this.loading = false;
        },
        destroy() {
            if (this.timeout) clearTimeout(this.timeout);
        }
    }"
    class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden min-h-[200px]"
>
    {{-- Loading State --}}
    <div x-show="loading && !error" class="flex flex-col items-center justify-center h-[65vh]">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin mb-2"></div>
        <span class="text-sm text-gray-500 dark:text-gray-400">Loading PDF...</span>
    </div>

    {{-- Error State --}}
    <div x-show="error" x-cloak class="flex flex-col items-center justify-center text-center p-6 h-[65vh]">
        {!! FileManagerIcon::DocumentText->render('w-12 h-12 text-amber-500 mb-3') !!}
        <p class="text-gray-700 dark:text-gray-300 font-medium">PDF preview unavailable</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            This PDF could not be loaded. It may be inaccessible or the URL has expired.
        </p>
        @if($url)
            <a
                href="{{ $url }}"
                download="{{ $item?->getName() ?? 'document.pdf' }}"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowDownTray->render('w-4 h-4') !!}
                Download PDF
            </a>
            <a
                href="{{ $url }}"
                target="_blank"
                class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowTopRightOnSquare->render('w-4 h-4') !!}
                Open in New Tab
            </a>
        @endif
    </div>

    {{-- PDF Viewer --}}
    <iframe
        x-show="loaded && !error"
        x-cloak
        src="{{ $url }}"
        class="w-full h-[65vh]"
        frameborder="0"
        title="{{ $item?->getName() ?? 'PDF preview' }}"
        x-on:load="handleLoad()"
        x-on:error="handleError()"
    ></iframe>
</div>
