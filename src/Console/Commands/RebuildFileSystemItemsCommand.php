<?php

namespace MWGuerra\FileManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;

class RebuildFileSystemItemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filemanager:rebuild
                            {--disk= : The storage disk to read from (defaults to FILEMANAGER_DISK or FILESYSTEM_DISK)}
                            {--root= : The root directory within the disk to scan}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear and rebuild the file_system_items table from the filesystem';

    /**
     * Counter for created items.
     */
    protected int $foldersCreated = 0;
    protected int $filesCreated = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = $this->option('disk')
            ?? config('filemanager.storage_mode.disk')
            ?? config('filemanager.upload.disk')
            ?? env('FILEMANAGER_DISK')
            ?? env('FILESYSTEM_DISK', 'public');

        $root = $this->option('root')
            ?? config('filemanager.storage_mode.root', '');

        // Validate disk exists (use directories() which works with S3-compatible storage)
        try {
            Storage::disk($disk)->directories('');
        } catch (\Exception $e) {
            $this->error("Disk '{$disk}' is not configured or accessible.");
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("FileManager Rebuild Command");
        $this->info("===========================");
        $this->newLine();
        $this->info("Disk: {$disk}");
        $this->info("Root: " . ($root ?: '(root)'));
        $this->newLine();

        // Get model class
        $modelClass = config('filemanager.model');

        if (!$modelClass || !class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");
            return self::FAILURE;
        }

        // Count existing records
        $existingCount = $modelClass::count();

        if ($existingCount > 0) {
            $this->warn("This will delete {$existingCount} existing record(s) from the file_system_items table.");
        }

        if (!$this->option('force') && !$this->confirm('Do you want to proceed?', false)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Starting rebuild...');
        $this->newLine();

        // Use a transaction for safety
        DB::beginTransaction();

        try {
            // Clear existing records
            $this->info('Clearing existing records...');
            $modelClass::query()->delete();
            $this->info("Deleted {$existingCount} record(s).");
            $this->newLine();

            // Scan and rebuild
            $this->info('Scanning filesystem...');
            $storage = Storage::disk($disk);

            $this->scanDirectory($storage, $modelClass, $root, null);

            DB::commit();

            $this->newLine();
            $this->info('Rebuild complete!');
            $this->table(
                ['Type', 'Count'],
                [
                    ['Folders', $this->foldersCreated],
                    ['Files', $this->filesCreated],
                    ['Total', $this->foldersCreated + $this->filesCreated],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred during rebuild:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->info('All changes have been rolled back.');

            return self::FAILURE;
        }
    }

    /**
     * Recursively scan a directory and create database records.
     */
    protected function scanDirectory($storage, string $modelClass, string $path, int|string|null $parentId): void
    {
        // Get directories and files
        $directories = $storage->directories($path);
        $files = $storage->files($path);

        // Process directories first
        foreach ($directories as $directory) {
            $name = basename($directory);

            // Skip hidden directories unless configured to show
            if (str_starts_with($name, '.') && !config('filemanager.storage_mode.show_hidden', false)) {
                continue;
            }

            $this->line("  Creating folder: {$directory}");

            $folder = $modelClass::create([
                'name' => $name,
                'type' => FileSystemItemType::Folder->value,
                'file_type' => null,
                'parent_id' => $parentId,
                'size' => null,
                'duration' => null,
                'thumbnail' => null,
                'storage_path' => $directory,
            ]);

            $this->foldersCreated++;

            // Recursively scan subdirectory
            $this->scanDirectory($storage, $modelClass, $directory, $folder->id);
        }

        // Process files
        foreach ($files as $file) {
            $name = basename($file);

            // Skip hidden files unless configured to show
            if (str_starts_with($name, '.') && !config('filemanager.storage_mode.show_hidden', false)) {
                continue;
            }

            $this->line("  Creating file: {$file}");

            // Get file info
            $size = null;
            $mimeType = null;

            try {
                $size = $storage->size($file);
                $mimeType = $storage->mimeType($file);
            } catch (\Exception $e) {
                $this->warn("    Could not get file info: {$e->getMessage()}");
            }

            // Determine file type from mime type
            $fileType = $mimeType ? FileType::fromMimeType($mimeType)->value : FileType::Other->value;

            $modelClass::create([
                'name' => $name,
                'type' => FileSystemItemType::File->value,
                'file_type' => $fileType,
                'parent_id' => $parentId,
                'size' => $size,
                'duration' => null, // Would need FFmpeg to get this
                'thumbnail' => null,
                'storage_path' => $file,
            ]);

            $this->filesCreated++;
        }
    }
}
