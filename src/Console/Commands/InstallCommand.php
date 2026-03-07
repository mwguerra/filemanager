<?php

namespace MWGuerra\FileManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filemanager:install
                            {--skip-assets : Skip publishing Filament assets}
                            {--skip-config : Skip publishing configuration}
                            {--skip-migrations : Skip running migrations}
                            {--with-css : Also configure your app.css for style customization}
                            {--css-path= : Path to the CSS file (defaults to resources/css/app.css)}
                            {--force : Overwrite existing configurations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install FileManager package with all required assets and configuration';

    /**
     * The @source directive to add for CSS customization.
     */
    protected string $sourceDirective = "@source '../../vendor/mwguerra/filemanager/resources/views/**/*.blade.php';";

    /**
     * The @variant dark directive to add.
     */
    protected string $variantDarkDirective = "@variant dark (&:where(.dark, .dark *));";

    /**
     * The primary color mappings for @theme block.
     */
    protected array $primaryColorMappings = [
        '--color-primary-50: var(--primary-50);',
        '--color-primary-100: var(--primary-100);',
        '--color-primary-200: var(--primary-200);',
        '--color-primary-300: var(--primary-300);',
        '--color-primary-400: var(--primary-400);',
        '--color-primary-500: var(--primary-500);',
        '--color-primary-600: var(--primary-600);',
        '--color-primary-700: var(--primary-700);',
        '--color-primary-800: var(--primary-800);',
        '--color-primary-900: var(--primary-900);',
        '--color-primary-950: var(--primary-950);',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║           FileManager Installation                        ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $steps = [];
        $hasErrors = false;

        // Determine if CSS configuration should run
        // If --css-path is provided, it implies --with-css
        $shouldConfigureCss = $this->option('with-css') || $this->option('css-path');

        // Step 1: Publish Filament assets (includes our pre-compiled CSS)
        if (!$this->option('skip-assets')) {
            $this->info('Step 1: Publishing Filament assets...');
            $exitCode = Artisan::call('filament:assets', [], $this->output);
            if ($exitCode === 0) {
                $steps[] = ['status' => 'success', 'message' => 'Filament assets published (includes FileManager CSS)'];
            } else {
                $steps[] = ['status' => 'warning', 'message' => 'Filament assets publish had issues'];
            }
            $this->newLine();
        }

        // Step 2: Publish configuration
        if (!$this->option('skip-config')) {
            $this->info('Step 2: Publishing configuration...');
            $exitCode = Artisan::call('vendor:publish', [
                '--tag' => 'filemanager-config',
                '--force' => $this->option('force'),
            ], $this->output);
            if ($exitCode === 0) {
                $steps[] = ['status' => 'success', 'message' => 'Configuration published to config/filemanager.php'];
            } else {
                $steps[] = ['status' => 'warning', 'message' => 'Configuration publish had issues'];
            }
            $this->newLine();
        }

        // Step 3: Run migrations
        if (!$this->option('skip-migrations')) {
            $this->info('Step 3: Publishing migrations...');
            $exitCode = Artisan::call('vendor:publish', [
                '--tag' => 'filemanager-migrations',
                '--force' => $this->option('force'),
            ], $this->output);
            if ($exitCode === 0) {
                $steps[] = ['status' => 'success', 'message' => 'Migrations published successfully'];
            } else {
                $steps[] = ['status' => 'warning', 'message' => 'Migrations publish had issues'];
            }
            $this->newLine();
        }

        // Step 4 (Optional): Configure app.css for style customization
        if ($shouldConfigureCss) {
            $this->info('Step 4: Configuring CSS for style customization...');
            $cssResult = $this->configureCss();

            // Check if there was an error (like file not found)
            foreach ($cssResult as $result) {
                if ($result['status'] === 'error') {
                    $hasErrors = true;
                }
            }

            $steps = array_merge($steps, $cssResult);
            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('Installation Summary');
        $this->info('═══════════════════');
        foreach ($steps as $step) {
            $icon = $step['status'] === 'success' ? '✓' : ($step['status'] === 'warning' ? '!' : ($step['status'] === 'info' ? 'ℹ' : '✗'));
            $color = $step['status'] === 'success' ? 'green' : ($step['status'] === 'warning' ? 'yellow' : ($step['status'] === 'info' ? 'cyan' : 'red'));
            $this->line("  <fg={$color}>{$icon}</> {$step['message']}");
        }

        // Return early with failure if there were errors
        if ($hasErrors) {
            $this->newLine();
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Installation complete!');
        $this->newLine();

        // Next steps
        $this->line('<fg=cyan>Next steps:</>');
        $this->line('  1. Register the plugin in your Panel Provider:');
        $this->newLine();
        $this->line('     <fg=gray>use MWGuerra\FileManager\FileManagerPlugin;</>');
        $this->newLine();
        $this->line('     <fg=gray>->plugins([</>');
        $this->line('     <fg=gray>    FileManagerPlugin::make(),</>');
        $this->line('     <fg=gray>])</>');
        $this->newLine();
        $this->line('  2. Access the File Manager at: /admin/file-manager');
        $this->newLine();

        if ($shouldConfigureCss) {
            $this->line('  3. Rebuild your CSS: npm run build');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Configure the CSS file for style customization.
     */
    protected function configureCss(): array
    {
        $cssPath = $this->option('css-path') ?? resource_path('css/app.css');
        $force = $this->option('force');
        $steps = [];
        $changesCount = 0;

        // Check if CSS file exists
        if (!File::exists($cssPath)) {
            $steps[] = ['status' => 'error', 'message' => "CSS file not found at: {$cssPath}"];
            return $steps;
        }

        // Read the CSS file
        $cssContent = File::get($cssPath);
        $originalContent = $cssContent;

        // 1. Add @source directive
        $result = $this->addSourceDirective($cssContent, $force);
        $cssContent = $result['content'];
        if ($result['added']) {
            $steps[] = ['status' => 'success', 'message' => 'Added @source directive for FileManager views'];
            $changesCount++;
        } elseif ($result['skipped']) {
            $steps[] = ['status' => 'info', 'message' => '@source directive already exists'];
        }

        // 2. Add @variant dark directive
        $result = $this->addVariantDarkDirective($cssContent, $force);
        $cssContent = $result['content'];
        if ($result['added']) {
            $steps[] = ['status' => 'success', 'message' => 'Added @variant dark directive'];
            $changesCount++;
        } elseif ($result['skipped']) {
            $steps[] = ['status' => 'info', 'message' => '@variant dark directive already exists'];
        }

        // 3. Add primary color mappings to @theme block
        $result = $this->addPrimaryColorMappings($cssContent, $force);
        $cssContent = $result['content'];
        if ($result['added']) {
            $steps[] = ['status' => 'success', 'message' => 'Primary color mappings added to @theme'];
            $changesCount++;
        } elseif ($result['skipped']) {
            $steps[] = ['status' => 'info', 'message' => 'Primary color mappings already exist'];
        }

        // Write the updated content if changed
        if ($cssContent !== $originalContent) {
            File::put($cssPath, $cssContent);
        }

        // If no changes were made, replace all info messages with a single summary
        if ($changesCount === 0) {
            $steps = [['status' => 'info', 'message' => 'No changes needed - CSS already configured']];
        }

        return $steps;
    }

    /**
     * Add the @source directive for FileManager views.
     */
    protected function addSourceDirective(string $content, bool $force): array
    {
        // Check if already exists
        if (str_contains($content, 'mwguerra/filemanager')) {
            if (!$force) {
                return ['content' => $content, 'added' => false, 'skipped' => true];
            }
        }

        // Find the best position to insert (after other @source directives or after @import)
        $lines = explode("\n", $content);
        $insertIndex = null;
        $lastSourceIndex = null;
        $lastImportIndex = null;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, '@source')) {
                $lastSourceIndex = $index;
            }
            if (str_starts_with($trimmedLine, '@import')) {
                $lastImportIndex = $index;
            }
        }

        // Determine insertion point
        if ($lastSourceIndex !== null) {
            $insertIndex = $lastSourceIndex + 1;
        } elseif ($lastImportIndex !== null) {
            $insertIndex = $lastImportIndex + 1;
            // Add a blank line after imports before the source directive
            array_splice($lines, $insertIndex, 0, ['']);
            $insertIndex++;
        } else {
            // Insert at the beginning
            $insertIndex = 0;
        }

        // Insert the source directive
        array_splice($lines, $insertIndex, 0, [$this->sourceDirective]);

        return [
            'content' => implode("\n", $lines),
            'added' => true,
            'skipped' => false,
        ];
    }

    /**
     * Add the @variant dark directive for Filament dark mode.
     */
    protected function addVariantDarkDirective(string $content, bool $force): array
    {
        // Check if already exists
        if (str_contains($content, '@variant dark')) {
            if (!$force) {
                return ['content' => $content, 'added' => false, 'skipped' => true];
            }
        }

        // Find the position to insert (after @source directives, before @theme)
        $lines = explode("\n", $content);
        $insertIndex = null;
        $lastSourceIndex = null;
        $themeIndex = null;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, '@source')) {
                $lastSourceIndex = $index;
            }
            if (str_starts_with($trimmedLine, '@theme')) {
                $themeIndex = $index;
            }
        }

        // Determine insertion point
        if ($themeIndex !== null) {
            // Insert before @theme block, with comment
            $insertIndex = $themeIndex;
            // Add blank line and comment
            $toInsert = [
                '',
                '/* Configure dark mode to use Filament\'s .dark class selector instead of prefers-color-scheme */',
                $this->variantDarkDirective,
            ];
        } elseif ($lastSourceIndex !== null) {
            // Insert after last @source
            $insertIndex = $lastSourceIndex + 1;
            $toInsert = [
                '',
                '/* Configure dark mode to use Filament\'s .dark class selector instead of prefers-color-scheme */',
                $this->variantDarkDirective,
            ];
        } else {
            // Insert at the end
            $insertIndex = count($lines);
            $toInsert = [
                '',
                '/* Configure dark mode to use Filament\'s .dark class selector instead of prefers-color-scheme */',
                $this->variantDarkDirective,
            ];
        }

        // Insert the lines
        array_splice($lines, $insertIndex, 0, $toInsert);

        return [
            'content' => implode("\n", $lines),
            'added' => true,
            'skipped' => false,
        ];
    }

    /**
     * Add primary color mappings to @theme block.
     */
    protected function addPrimaryColorMappings(string $content, bool $force): array
    {
        // Check if primary color mappings already exist
        if (str_contains($content, '--color-primary-500: var(--primary-500)')) {
            if (!$force) {
                return ['content' => $content, 'added' => false, 'skipped' => true];
            }
        }

        // Check if @theme block exists
        if (str_contains($content, '@theme')) {
            // Insert into existing @theme block
            return $this->insertIntoThemeBlock($content);
        } else {
            // Create new @theme block
            return $this->createThemeBlock($content);
        }
    }

    /**
     * Insert primary color mappings into existing @theme block.
     */
    protected function insertIntoThemeBlock(string $content): array
    {
        $lines = explode("\n", $content);
        $themeStartIndex = null;
        $themeEndIndex = null;
        $braceCount = 0;
        $inTheme = false;

        // Find the @theme block boundaries
        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            if (str_starts_with($trimmedLine, '@theme')) {
                $themeStartIndex = $index;
                $inTheme = true;
            }

            if ($inTheme) {
                $braceCount += substr_count($line, '{');
                $braceCount -= substr_count($line, '}');

                if ($braceCount === 0 && $themeStartIndex !== null && $index > $themeStartIndex) {
                    $themeEndIndex = $index;
                    break;
                }
            }
        }

        if ($themeStartIndex === null || $themeEndIndex === null) {
            // Couldn't parse @theme block, create a new one
            return $this->createThemeBlock($content);
        }

        // Build the color mappings string with proper indentation
        $mappingsComment = "\n    /* Map Filament's primary color custom properties to Tailwind color utilities */";
        $mappings = '';
        foreach ($this->primaryColorMappings as $mapping) {
            $mappings .= "\n    {$mapping}";
        }

        // Insert before the closing brace of @theme
        $insertIndex = $themeEndIndex;
        array_splice($lines, $insertIndex, 0, [$mappingsComment . $mappings]);

        return [
            'content' => implode("\n", $lines),
            'added' => true,
            'skipped' => false,
        ];
    }

    /**
     * Create a new @theme block with primary color mappings.
     */
    protected function createThemeBlock(string $content): array
    {
        $themeBlock = "\n@theme {\n";
        $themeBlock .= "    /* Map Filament's primary color custom properties to Tailwind color utilities */\n";
        foreach ($this->primaryColorMappings as $mapping) {
            $themeBlock .= "    {$mapping}\n";
        }
        $themeBlock .= "}\n";

        // Append to the end of the file
        $content = rtrim($content) . "\n" . $themeBlock;

        return [
            'content' => $content,
            'added' => true,
            'skipped' => false,
        ];
    }
}
