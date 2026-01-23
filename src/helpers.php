<?php

use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\FileManager\Enums\FileManagerIcon;

if (! function_exists('fmicon')) {
    /**
     * Render a file manager icon.
     *
     * @param FileManagerIcon|string $icon The icon to render (enum case or string name)
     * @param string $class CSS classes to apply to the icon
     * @return Htmlable The rendered icon
     *
     * @example
     * // Using string name:
     * {!! fmicon('folder', 'w-4 h-4 text-primary-500') !!}
     *
     * // Using enum:
     * {!! fmicon(FileManagerIcon::Folder, 'w-4 h-4 text-primary-500') !!}
     */
    function fmicon(FileManagerIcon|string $icon, string $class = ''): Htmlable
    {
        if (is_string($icon)) {
            $iconEnum = FileManagerIcon::fromString($icon);

            if ($iconEnum === null) {
                throw new InvalidArgumentException("Unknown file manager icon: {$icon}");
            }

            $icon = $iconEnum;
        }

        return $icon->render($class);
    }
}
