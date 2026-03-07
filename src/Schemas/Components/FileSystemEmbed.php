<?php

namespace MWGuerra\FileManager\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Livewire;
use MWGuerra\FileManager\Livewire\EmbeddedFileSystem;

/**
 * Embeddable File System component for use in Filament schemas/forms.
 *
 * This component embeds the storage-mode file browser into any Filament form or page.
 * Extends Filament's built-in Livewire component for proper component isolation.
 */
class FileSystemEmbed extends Livewire
{
    protected string|Closure $height = '500px';

    protected bool|Closure $showHeader = true;

    protected bool|Closure $showSidebar = true;

    protected string|Closure $defaultViewMode = 'grid';

    protected string|Closure|null $disk = null;

    protected string|Closure|null $target = null;

    protected string|Closure|null $initialFolder = null;

    protected string|Closure $sidebarRootLabel = 'Root';

    protected string|Closure $sidebarHeading = 'Folders';

    protected string|Closure $breadcrumbsRootLabel = 'Root';

    public static function make(Closure|string|null $component = null, Closure|array $data = []): static
    {
        $static = app(static::class, [
            'component' => $component ?? EmbeddedFileSystem::class,
            'data' => $data,
        ]);
        $static->configure();
        $static->key('embedded-file-system');

        return $static;
    }

    /**
     * Get the properties to pass to the Livewire component.
     *
     * @return array<string, mixed>
     */
    public function getComponentProperties(): array
    {
        return [
            ...parent::getComponentProperties(),
            'height' => $this->getHeight(),
            'showHeader' => $this->shouldShowHeader(),
            'showSidebar' => $this->shouldShowSidebar(),
            'defaultViewMode' => $this->getDefaultViewMode(),
            'disk' => $this->getDisk(),
            'target' => $this->getTarget(),
            'initialFolder' => $this->getInitialFolder(),
            'sidebarRootLabel' => $this->getSidebarRootLabel(),
            'sidebarHeading' => $this->getSidebarHeading(),
            'breadcrumbsRootLabel' => $this->getBreadcrumbsRootLabel(),
        ];
    }

    /**
     * Set the height of the embedded file system browser.
     */
    public function height(string|Closure $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get the height of the embedded file system browser.
     */
    public function getHeight(): string
    {
        return $this->evaluate($this->height);
    }

    /**
     * Show or hide the header controls.
     */
    public function showHeader(bool|Closure $show = true): static
    {
        $this->showHeader = $show;

        return $this;
    }

    /**
     * Hide the header controls.
     */
    public function hideHeader(): static
    {
        return $this->showHeader(false);
    }

    /**
     * Get whether to show the header.
     */
    public function shouldShowHeader(): bool
    {
        return $this->evaluate($this->showHeader);
    }

    /**
     * Show or hide the sidebar.
     */
    public function showSidebar(bool|Closure $show = true): static
    {
        $this->showSidebar = $show;

        return $this;
    }

    /**
     * Hide the sidebar.
     */
    public function hideSidebar(): static
    {
        return $this->showSidebar(false);
    }

    /**
     * Get whether to show the sidebar.
     */
    public function shouldShowSidebar(): bool
    {
        return $this->evaluate($this->showSidebar);
    }

    /**
     * Set the default view mode (grid or list).
     */
    public function defaultViewMode(string|Closure $mode): static
    {
        $this->defaultViewMode = $mode;

        return $this;
    }

    /**
     * Get the default view mode.
     */
    public function getDefaultViewMode(): string
    {
        return $this->evaluate($this->defaultViewMode);
    }

    /**
     * Set the storage disk to use.
     */
    public function disk(string|Closure|null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Get the storage disk.
     */
    public function getDisk(): ?string
    {
        return $this->evaluate($this->disk);
    }

    /**
     * Set the target directory within the disk.
     */
    public function target(string|Closure|null $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Get the target directory.
     */
    public function getTarget(): ?string
    {
        return $this->evaluate($this->target);
    }

    /**
     * Set the initial folder path to navigate to on load.
     * For storage mode, this is the folder path within the disk.
     */
    public function initialFolder(string|Closure|null $folder): static
    {
        $this->initialFolder = $folder;

        return $this;
    }

    /**
     * Get the initial folder.
     */
    public function getInitialFolder(): ?string
    {
        return $this->evaluate($this->initialFolder);
    }

    /**
     * Set to compact mode (no header, no sidebar).
     */
    public function compact(): static
    {
        return $this->hideHeader()->hideSidebar();
    }

    /**
     * Set the sidebar root label.
     */
    public function sidebarRootLabel(string|Closure $label): static
    {
        $this->sidebarRootLabel = $label;

        return $this;
    }

    /**
     * Get the sidebar root label.
     */
    public function getSidebarRootLabel(): string
    {
        return $this->evaluate($this->sidebarRootLabel);
    }

    /**
     * Set the sidebar heading.
     */
    public function sidebarHeading(string|Closure $heading): static
    {
        $this->sidebarHeading = $heading;

        return $this;
    }

    /**
     * Get the sidebar heading.
     */
    public function getSidebarHeading(): string
    {
        return $this->evaluate($this->sidebarHeading);
    }

    /**
     * Configure sidebar with all options at once.
     */
    public function sidebar(bool|Closure $show = true, string|Closure|null $rootLabel = null, string|Closure|null $heading = null): static
    {
        $this->showSidebar($show);

        if ($rootLabel !== null) {
            $this->sidebarRootLabel($rootLabel);
        }

        if ($heading !== null) {
            $this->sidebarHeading($heading);
        }

        return $this;
    }

    /**
     * Set the breadcrumbs root label.
     */
    public function breadcrumbsRootLabel(string|Closure $label): static
    {
        $this->breadcrumbsRootLabel = $label;

        return $this;
    }

    /**
     * Get the breadcrumbs root label.
     */
    public function getBreadcrumbsRootLabel(): string
    {
        return $this->evaluate($this->breadcrumbsRootLabel);
    }
}
