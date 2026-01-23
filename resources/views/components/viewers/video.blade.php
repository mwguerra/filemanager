{{--
Video Viewer Component with error handling

Variables:
- $url: The URL of the video file
- $item: The FileSystemItem model (optional)
--}}
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $url = $url ?? null;
    $item = $item ?? null;
    // Create a unique key to force Alpine re-initialization when URL changes
    $viewerKey = 'vid-' . md5($url ?? '');
@endphp

<div
    wire:key="{{ $viewerKey }}"
    x-data="{
        loaded: false,
        error: false,
        loading: true,
        checkLoaded() {
            // Handle cached videos that load before Alpine initializes
            const video = this.$refs.previewVideo;
            if (video && video.readyState >= 1) {
                this.loaded = true;
                this.loading = false;
                // Start playback after state is updated
                video.play().catch(() => {});
            }
        },
        handleLoaded() {
            this.loaded = true;
            this.loading = false;
            // Start playback after state is updated
            this.$refs.previewVideo?.play().catch(() => {});
        }
    }"
    x-init="$nextTick(() => checkLoaded())"
    class="flex items-center justify-center bg-black rounded-lg overflow-hidden min-h-[200px]"
>
    {{-- Loading State --}}
    <div x-show="loading && !error" class="flex flex-col items-center justify-center text-white">
        <div class="w-8 h-8 border-4 border-white border-t-transparent rounded-full animate-spin mb-2"></div>
        <span class="text-sm text-gray-300">Loading video...</span>
    </div>

    {{-- Error State --}}
    <div x-show="error" x-cloak class="flex flex-col items-center justify-center text-center p-6 text-white">
        {!! FileManagerIcon::VideoCameraSlash->render('w-12 h-12 text-amber-500 mb-3') !!}
        <p class="font-medium">Video unavailable</p>
        <p class="text-sm text-gray-400 mt-1">
            This video could not be loaded. It may be inaccessible or the URL has expired.
        </p>
        @if($url)
            <a
                href="{{ $url }}"
                download="{{ $item?->getName() ?? 'download' }}"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors text-sm"
            >
                {!! FileManagerIcon::ArrowDownTray->render('w-4 h-4') !!}
                Download Video
            </a>
        @endif
    </div>

    {{-- Video Player --}}
    <video
        x-ref="previewVideo"
        x-show="loaded && !error"
        x-cloak
        controls
        class="max-w-full max-h-[65vh]"
        preload="metadata"
        @if($item && method_exists($item, 'getThumbnail') && $item->getThumbnail())
            poster="{{ $item->getThumbnail() }}"
        @endif
        x-on:loadeddata="handleLoaded()"
        x-on:error="error = true; loading = false"
    >
        <source src="{{ $url }}" type="video/mp4">
        <source src="{{ $url }}" type="video/webm">
        <source src="{{ $url }}" type="video/ogg">
        Your browser does not support the video tag.
    </video>
</div>
