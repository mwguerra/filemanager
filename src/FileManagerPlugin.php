<?php

namespace MWGuerra\FileManager;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use MWGuerra\FileManager\Enums\FileManagerIcon;
use MWGuerra\FileManager\Filament\Pages\EmbedConfigTest;
use MWGuerra\FileManager\Filament\Pages\FileManager;
use MWGuerra\FileManager\Filament\Pages\FileSystem;
use MWGuerra\FileManager\Filament\Pages\SchemaExample;
use MWGuerra\FileManager\Filament\Resources\FileSystemItemResource;

class FileManagerPlugin implements Plugin
{
    /**
     * Singleton instance for accessing configuration from pages/components.
     */
    protected static ?self $currentInstance = null;

    /**
     * Components to register (pages and resources).
     * If null, all enabled components are registered based on config.
     *
     * @var array<class-string>|null
     */
    protected ?array $components = null;

    /**
     * All available page classes.
     *
     * @var array<class-string>
     */
    protected array $availablePages = [
        FileManager::class,
        FileSystem::class,
        SchemaExample::class,
        EmbedConfigTest::class,
    ];

    /**
     * All available resource classes.
     *
     * @var array<class-string>
     */
    protected array $availableResources = [
        FileSystemItemResource::class,
    ];

    // =========================================================================
    // Panel Sidebar Configuration
    // =========================================================================

    protected bool $panelSidebarEnabled = false;
    protected string $panelSidebarRenderHook = PanelsRenderHook::SIDEBAR_NAV_START;
    protected ?array $panelSidebarScopes = null;
    protected ?string $panelSidebarRootLabel = null;
    protected ?string $panelSidebarHeading = null;

    // =========================================================================
    // File Manager Page Configuration
    // =========================================================================

    protected bool $fileManagerEnabled = true;
    protected bool $fileManagerPageSidebarEnabled = true;
    protected ?string $fileManagerNavigationIcon = null;
    protected ?string $fileManagerNavigationLabel = null;
    protected ?int $fileManagerNavigationSort = null;
    protected ?string $fileManagerNavigationGroup = null;
    protected bool $fileManagerNavigationGroupSet = false;
    protected ?string $fileManagerSidebarRootLabel = null;
    protected ?string $fileManagerSidebarHeading = null;

    // =========================================================================
    // File System Page Configuration
    // =========================================================================

    protected bool $fileSystemEnabled = true;
    protected bool $fileSystemPageSidebarEnabled = true;
    protected ?string $fileSystemNavigationIcon = null;
    protected ?string $fileSystemNavigationLabel = null;
    protected ?int $fileSystemNavigationSort = null;
    protected ?string $fileSystemNavigationGroup = null;
    protected bool $fileSystemNavigationGroupSet = false;
    protected ?string $fileSystemSidebarRootLabel = null;
    protected ?string $fileSystemSidebarHeading = null;

    // =========================================================================
    // Schema Example Page Configuration
    // =========================================================================

    protected bool $schemaExampleEnabled = true;

    // =========================================================================
    // Embed Config Test Page Configuration (for E2E testing)
    // =========================================================================

    protected bool $embedConfigTestEnabled = true;

    // =========================================================================
    // Icon Configuration
    // =========================================================================

    /**
     * Whether icons are enabled globally.
     */
    protected bool $iconsEnabled = true;

    /**
     * Custom icon overrides.
     * Keys are icon names (e.g., 'folder', 'document').
     * Values can be icon names (e.g., 'phosphor-folder') or raw SVG strings.
     *
     * @var array<string, string>
     */
    protected array $iconOverrides = [];

    // =========================================================================
    // Plugin Interface Methods
    // =========================================================================

    public function getId(): string
    {
        return 'filemanager';
    }

    public function register(Panel $panel): void
    {
        // Store instance for access from pages/components
        static::$currentInstance = $this;

        $pages = [];
        $resources = [];

        if ($this->components !== null) {
            // Register only specified components
            foreach ($this->components as $component) {
                if (in_array($component, $this->availablePages, true)) {
                    $pages[] = $component;
                } elseif (in_array($component, $this->availableResources, true)) {
                    $resources[] = $component;
                }
            }
        } else {
            // Register all components based on fluent config and fallback to config file
            if ($this->fileManagerEnabled && config('filemanager.file_manager.enabled', true)) {
                $pages[] = FileManager::class;
            }

            if ($this->fileSystemEnabled && config('filemanager.file_system.enabled', true)) {
                $pages[] = FileSystem::class;
            }

            if ($this->schemaExampleEnabled && config('filemanager.schema_example.enabled', true)) {
                $pages[] = SchemaExample::class;
            }

            // Register test page only in local/testing environments
            if ($this->embedConfigTestEnabled && app()->environment('testing', 'local')) {
                $pages[] = EmbedConfigTest::class;
            }

            $resources[] = FileSystemItemResource::class;
        }

        if (!empty($pages)) {
            $panel->pages($pages);
        }

        if (!empty($resources)) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
        // Store instance for access from pages/components
        static::$currentInstance = $this;

        if ($this->panelSidebarEnabled) {
            $this->registerPanelSidebarRenderHook();
        }
    }

    /**
     * Register the panel sidebar render hook.
     */
    protected function registerPanelSidebarRenderHook(): void
    {
        FilamentView::registerRenderHook(
            $this->panelSidebarRenderHook,
            fn (): View => view('filemanager::components.panel-sidebar'),
            scopes: $this->panelSidebarScopes,
        );
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a new plugin instance.
     *
     * @param array<class-string>|null $components Optional array of page/resource classes to register.
     *                                              If null, all enabled components are registered.
     *
     * @example
     * // Register all enabled components (based on config)
     * FileManagerPlugin::make()
     *
     * @example
     * // Register only specific components
     * FileManagerPlugin::make([
     *     FileManager::class,
     *     FileSystem::class,
     * ])
     */
    public static function make(?array $components = null): static
    {
        $plugin = app(static::class);
        $plugin->components = $components;

        return $plugin;
    }

    /**
     * Get the current plugin instance.
     */
    public static function get(): static
    {
        if (static::$currentInstance !== null) {
            return static::$currentInstance;
        }

        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Get the current plugin instance or null if not registered.
     */
    public static function current(): ?static
    {
        return static::$currentInstance;
    }

    // =========================================================================
    // Component Selection Methods
    // =========================================================================

    /**
     * Set the components to register.
     *
     * @param array<class-string> $components
     */
    public function components(array $components): static
    {
        $this->components = $components;

        return $this;
    }

    /**
     * Register only the specified pages.
     *
     * @param array<class-string> $pages
     */
    public function only(array $pages): static
    {
        $this->components = $pages;

        return $this;
    }

    // =========================================================================
    // Panel Sidebar Configuration (appears in Filament panel navigation)
    // =========================================================================

    /**
     * Enable the sidebar folder tree in the panel navigation.
     *
     * @param bool $enabled Whether to enable the panel sidebar
     * @param string $renderHook The render hook to use (default: SIDEBAR_NAV_START)
     * @param array|null $scopes Optional scopes for the render hook
     *
     * @example
     * // Enable panel sidebar at the start of navigation
     * FileManagerPlugin::make()->panelSidebar()
     *
     * @example
     * // Enable panel sidebar at the end of navigation
     * FileManagerPlugin::make()->panelSidebar(renderHook: PanelsRenderHook::SIDEBAR_NAV_END)
     */
    public function panelSidebar(
        bool $enabled = true,
        string $renderHook = PanelsRenderHook::SIDEBAR_NAV_START,
        ?array $scopes = null
    ): static {
        $this->panelSidebarEnabled = $enabled;
        $this->panelSidebarRenderHook = $renderHook;
        $this->panelSidebarScopes = $scopes;

        return $this;
    }

    /**
     * Alias for panelSidebar() for backward compatibility.
     */
    public function sidebar(
        string $renderHook = PanelsRenderHook::SIDEBAR_NAV_START,
        ?array $scopes = null
    ): static {
        return $this->panelSidebar(true, $renderHook, $scopes);
    }

    /**
     * Set the root label for the panel sidebar.
     */
    public function panelSidebarRootLabel(string $label): static
    {
        $this->panelSidebarRootLabel = $label;

        return $this;
    }

    /**
     * Set the heading for the panel sidebar.
     */
    public function panelSidebarHeading(string $heading): static
    {
        $this->panelSidebarHeading = $heading;

        return $this;
    }

    /**
     * Disable the panel sidebar.
     */
    public function withoutPanelSidebar(): static
    {
        $this->panelSidebarEnabled = false;

        return $this;
    }

    /**
     * Check if panel sidebar is enabled.
     */
    public function isPanelSidebarEnabled(): bool
    {
        return $this->panelSidebarEnabled;
    }

    /**
     * Get the panel sidebar root label.
     */
    public function getPanelSidebarRootLabel(): string
    {
        return $this->panelSidebarRootLabel
            ?? config('filemanager.sidebar.root_label')
            ?? 'Root';
    }

    /**
     * Get the panel sidebar heading.
     */
    public function getPanelSidebarHeading(): string
    {
        return $this->panelSidebarHeading
            ?? config('filemanager.sidebar.heading')
            ?? 'Folders';
    }

    // Backward compatibility alias
    public function isSidebarEnabled(): bool
    {
        return $this->isPanelSidebarEnabled();
    }

    // =========================================================================
    // File Manager Page Configuration
    // =========================================================================

    /**
     * Configure the File Manager page (database mode).
     *
     * @example
     * FileManagerPlugin::make()
     *     ->fileManager()
     *     ->fileManagerNavigation(icon: 'heroicon-o-folder', label: 'Files', sort: 1)
     *     ->fileManagerPageSidebar(true)
     */
    public function fileManager(bool $enabled = true): static
    {
        $this->fileManagerEnabled = $enabled;

        return $this;
    }

    /**
     * Disable the File Manager page.
     */
    public function withoutFileManager(): static
    {
        $this->fileManagerEnabled = false;

        return $this;
    }

    /**
     * Configure the page sidebar for File Manager.
     */
    public function fileManagerPageSidebar(bool $enabled = true): static
    {
        $this->fileManagerPageSidebarEnabled = $enabled;

        return $this;
    }

    /**
     * Set the root label for the File Manager page sidebar.
     */
    public function fileManagerSidebarRootLabel(string $label): static
    {
        $this->fileManagerSidebarRootLabel = $label;

        return $this;
    }

    /**
     * Set the heading for the File Manager page sidebar.
     */
    public function fileManagerSidebarHeading(string $heading): static
    {
        $this->fileManagerSidebarHeading = $heading;

        return $this;
    }

    /**
     * Configure navigation for the File Manager page.
     *
     * @param string|null $icon Navigation icon
     * @param string|null $label Navigation label
     * @param int|null $sort Navigation sort order
     * @param string|null|false $group Navigation group. Pass null to remove from any group, false to use default.
     */
    public function fileManagerNavigation(
        ?string $icon = null,
        ?string $label = null,
        ?int $sort = null,
        string|null|false $group = false
    ): static {
        if ($icon !== null) {
            $this->fileManagerNavigationIcon = $icon;
        }
        if ($label !== null) {
            $this->fileManagerNavigationLabel = $label;
        }
        if ($sort !== null) {
            $this->fileManagerNavigationSort = $sort;
        }
        if ($group !== false) {
            $this->fileManagerNavigationGroup = $group;
            $this->fileManagerNavigationGroupSet = true;
        }

        return $this;
    }

    /**
     * Check if File Manager is enabled.
     */
    public function isFileManagerEnabled(): bool
    {
        return $this->fileManagerEnabled;
    }

    /**
     * Check if File Manager page sidebar is enabled.
     */
    public function isFileManagerPageSidebarEnabled(): bool
    {
        return $this->fileManagerPageSidebarEnabled;
    }

    /**
     * Get the File Manager navigation icon.
     */
    public function getFileManagerNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->fileManagerNavigationIcon
            ?? config('filemanager.file_manager.navigation.icon', 'heroicon-o-folder');
    }

    /**
     * Get the File Manager navigation label.
     */
    public function getFileManagerNavigationLabel(): string
    {
        return $this->fileManagerNavigationLabel
            ?? config('filemanager.file_manager.navigation.label', 'File Manager');
    }

    /**
     * Get the File Manager navigation sort.
     */
    public function getFileManagerNavigationSort(): ?int
    {
        return $this->fileManagerNavigationSort
            ?? config('filemanager.file_manager.navigation.sort', 1);
    }

    /**
     * Get the File Manager navigation group.
     * Returns null if explicitly set to null (no group), otherwise returns config default.
     */
    public function getFileManagerNavigationGroup(): ?string
    {
        if ($this->fileManagerNavigationGroupSet) {
            return $this->fileManagerNavigationGroup;
        }

        return config('filemanager.file_manager.navigation.group', 'FileManager');
    }

    /**
     * Get the File Manager sidebar root label.
     */
    public function getFileManagerSidebarRootLabel(): string
    {
        return $this->fileManagerSidebarRootLabel
            ?? $this->panelSidebarRootLabel
            ?? config('filemanager.sidebar.root_label')
            ?? 'Root';
    }

    /**
     * Get the File Manager sidebar heading.
     */
    public function getFileManagerSidebarHeading(): string
    {
        return $this->fileManagerSidebarHeading
            ?? $this->panelSidebarHeading
            ?? config('filemanager.sidebar.heading')
            ?? 'Folders';
    }

    // =========================================================================
    // File System Page Configuration
    // =========================================================================

    /**
     * Configure the File System page (storage mode).
     *
     * @example
     * FileManagerPlugin::make()
     *     ->fileSystem()
     *     ->fileSystemNavigation(icon: 'heroicon-o-server-stack', label: 'Storage')
     */
    public function fileSystem(bool $enabled = true): static
    {
        $this->fileSystemEnabled = $enabled;

        return $this;
    }

    /**
     * Disable the File System page.
     */
    public function withoutFileSystem(): static
    {
        $this->fileSystemEnabled = false;

        return $this;
    }

    /**
     * Configure the page sidebar for File System.
     */
    public function fileSystemPageSidebar(bool $enabled = true): static
    {
        $this->fileSystemPageSidebarEnabled = $enabled;

        return $this;
    }

    /**
     * Set the root label for the File System page sidebar.
     */
    public function fileSystemSidebarRootLabel(string $label): static
    {
        $this->fileSystemSidebarRootLabel = $label;

        return $this;
    }

    /**
     * Set the heading for the File System page sidebar.
     */
    public function fileSystemSidebarHeading(string $heading): static
    {
        $this->fileSystemSidebarHeading = $heading;

        return $this;
    }

    /**
     * Configure navigation for the File System page.
     *
     * @param string|null $icon Navigation icon
     * @param string|null $label Navigation label
     * @param int|null $sort Navigation sort order
     * @param string|null|false $group Navigation group. Pass null to remove from any group, false to use default.
     */
    public function fileSystemNavigation(
        ?string $icon = null,
        ?string $label = null,
        ?int $sort = null,
        string|null|false $group = false
    ): static {
        if ($icon !== null) {
            $this->fileSystemNavigationIcon = $icon;
        }
        if ($label !== null) {
            $this->fileSystemNavigationLabel = $label;
        }
        if ($sort !== null) {
            $this->fileSystemNavigationSort = $sort;
        }
        if ($group !== false) {
            $this->fileSystemNavigationGroup = $group;
            $this->fileSystemNavigationGroupSet = true;
        }

        return $this;
    }

    /**
     * Check if File System is enabled.
     */
    public function isFileSystemEnabled(): bool
    {
        return $this->fileSystemEnabled;
    }

    /**
     * Check if File System page sidebar is enabled.
     */
    public function isFileSystemPageSidebarEnabled(): bool
    {
        return $this->fileSystemPageSidebarEnabled;
    }

    /**
     * Get the File System navigation icon.
     */
    public function getFileSystemNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->fileSystemNavigationIcon
            ?? config('filemanager.file_system.navigation.icon', 'heroicon-o-server-stack');
    }

    /**
     * Get the File System navigation label.
     */
    public function getFileSystemNavigationLabel(): string
    {
        return $this->fileSystemNavigationLabel
            ?? config('filemanager.file_system.navigation.label', 'File System');
    }

    /**
     * Get the File System navigation sort.
     */
    public function getFileSystemNavigationSort(): ?int
    {
        return $this->fileSystemNavigationSort
            ?? config('filemanager.file_system.navigation.sort', 2);
    }

    /**
     * Get the File System navigation group.
     * Returns null if explicitly set to null (no group), otherwise returns config default.
     */
    public function getFileSystemNavigationGroup(): ?string
    {
        if ($this->fileSystemNavigationGroupSet) {
            return $this->fileSystemNavigationGroup;
        }

        return config('filemanager.file_system.navigation.group')
            ?? config('filemanager.file_manager.navigation.group', 'FileManager');
    }

    /**
     * Get the File System sidebar root label.
     */
    public function getFileSystemSidebarRootLabel(): string
    {
        return $this->fileSystemSidebarRootLabel
            ?? $this->panelSidebarRootLabel
            ?? config('filemanager.sidebar.root_label')
            ?? 'Root';
    }

    /**
     * Get the File System sidebar heading.
     */
    public function getFileSystemSidebarHeading(): string
    {
        return $this->fileSystemSidebarHeading
            ?? $this->panelSidebarHeading
            ?? config('filemanager.sidebar.heading')
            ?? 'Folders';
    }

    // =========================================================================
    // Schema Example Page Configuration
    // =========================================================================

    /**
     * Enable the Schema Example page.
     */
    public function schemaExample(bool $enabled = true): static
    {
        $this->schemaExampleEnabled = $enabled;

        return $this;
    }

    /**
     * Disable the Schema Example page.
     */
    public function withoutSchemaExample(): static
    {
        $this->schemaExampleEnabled = false;

        return $this;
    }

    /**
     * Check if Schema Example is enabled.
     */
    public function isSchemaExampleEnabled(): bool
    {
        return $this->schemaExampleEnabled;
    }

    // =========================================================================
    // Icon Configuration
    // =========================================================================

    /**
     * Disable all icons in the file manager.
     *
     * When icons are disabled, FileManagerIcon::render() returns an empty string.
     * This is useful for accessibility or performance optimizations.
     *
     * @example
     * FileManagerPlugin::make()->noIcons()
     */
    public function noIcons(): static
    {
        $this->iconsEnabled = false;

        return $this;
    }

    /**
     * Enable icons in the file manager (default).
     *
     * @example
     * FileManagerPlugin::make()->withIcons()
     */
    public function withIcons(): static
    {
        $this->iconsEnabled = true;

        return $this;
    }

    /**
     * Check if icons are enabled.
     */
    public function areIconsEnabled(): bool
    {
        return $this->iconsEnabled;
    }

    /**
     * Configure custom icons for the file manager.
     *
     * You can override any icon used in the file manager by providing either:
     * - An icon name (e.g., 'phosphor-folder', 'tabler-file')
     * - A raw SVG string (e.g., '<svg>...</svg>')
     *
     * @param array<string, string> $icons Map of icon names to custom values
     *
     * @example
     * FileManagerPlugin::make()
     *     ->icons([
     *         'folder' => 'phosphor-folder',
     *         'document' => '<svg xmlns="http://www.w3.org/2000/svg">...</svg>',
     *     ])
     */
    public function icons(array $icons): static
    {
        $this->iconOverrides = array_merge($this->iconOverrides, $icons);

        return $this;
    }

    /**
     * Set a single icon override.
     *
     * @param FileManagerIcon|string $icon The icon to override
     * @param string $value The custom icon name or SVG string
     */
    public function icon(FileManagerIcon|string $icon, string $value): static
    {
        $key = $icon instanceof FileManagerIcon ? $icon->value : $icon;
        $this->iconOverrides[$key] = $value;

        return $this;
    }

    /**
     * Get the custom override for a specific icon.
     *
     * @param FileManagerIcon|string $icon The icon to get override for
     * @return string|null The custom value or null if not overridden
     */
    public function getIconOverride(FileManagerIcon|string $icon): ?string
    {
        $key = $icon instanceof FileManagerIcon ? $icon->value : $icon;

        return $this->iconOverrides[$key] ?? null;
    }

    /**
     * Get all icon overrides.
     *
     * @return array<string, string>
     */
    public function getIconOverrides(): array
    {
        return $this->iconOverrides;
    }
}
