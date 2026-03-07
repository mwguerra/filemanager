<?php

namespace MWGuerra\FileManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Enums\FileSystemItemType;
use MWGuerra\FileManager\Enums\FileType;

class UploadFolderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filemanager:upload
                            {path : The local folder path to upload}
                            {--disk= : The storage disk to upload to (defaults to FILEMANAGER_DISK or FILESYSTEM_DISK)}
                            {--target= : The target directory within the disk (defaults to root)}
                            {--no-database : Skip creating database records}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload a local folder to the configured storage disk';

    /**
     * Counter for uploaded items.
     */
    protected int $foldersCreated = 0;
    protected int $filesUploaded = 0;
    protected int $filesSkipped = 0;
    protected int $bytesCopied = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $localPath = $this->argument('path');

        // Validate local path exists and is a directory
        if (!File::exists($localPath)) {
            $this->error("Path does not exist: {$localPath}");
            return self::FAILURE;
        }

        if (!File::isDirectory($localPath)) {
            $this->error("Path is not a directory: {$localPath}");
            return self::FAILURE;
        }

        $disk = $this->option('disk')
            ?? config('filemanager.storage_mode.disk')
            ?? config('filemanager.upload.disk')
            ?? env('FILEMANAGER_DISK')
            ?? env('FILESYSTEM_DISK', 'public');

        $target = $this->option('target') ?? '';
        $createDatabase = !$this->option('no-database');

        // Validate disk exists (use directories() which works with S3-compatible storage)
        try {
            Storage::disk($disk)->directories('');
        } catch (\Exception $e) {
            $this->error("Disk '{$disk}' is not configured or accessible.");
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Count local files
        $localFiles = $this->countLocalItems($localPath);

        $this->info("FileManager Upload Command");
        $this->info("==========================");
        $this->newLine();
        $this->info("Source: {$localPath}");
        $this->info("Disk: {$disk}");
        $this->info("Target: " . ($target ?: '(root)'));
        $this->info("Database records: " . ($createDatabase ? 'Yes' : 'No'));
        $this->newLine();
        $this->info("Found {$localFiles['folders']} folder(s) and {$localFiles['files']} file(s) to upload.");
        $this->info("Total size: " . $this->formatBytes($localFiles['size']));
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Do you want to proceed?', false)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Starting upload...');
        $this->newLine();

        $storage = Storage::disk($disk);
        $modelClass = null;

        if ($createDatabase) {
            $modelClass = config('filemanager.model');

            if (!$modelClass || !class_exists($modelClass)) {
                $this->error("Model class not found: {$modelClass}");
                return self::FAILURE;
            }
        }

        // Use a transaction for database operations
        if ($createDatabase) {
            DB::beginTransaction();
        }

        try {
            // Find or create target folder in database if needed
            $parentId = null;
            if ($createDatabase && $target) {
                $parentId = $this->ensureTargetFolder($modelClass, $storage, $target);
            }

            // Upload the folder contents
            $this->uploadDirectory($localPath, $storage, $target, $modelClass, $parentId);

            if ($createDatabase) {
                DB::commit();
            }

            $this->newLine();
            $this->info('Upload complete!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Folders created', $this->foldersCreated],
                    ['Files uploaded', $this->filesUploaded],
                    ['Files skipped', $this->filesSkipped],
                    ['Data transferred', $this->formatBytes($this->bytesCopied)],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            if ($createDatabase) {
                DB::rollBack();
            }
            $this->error('An error occurred during upload:');
            $this->error($e->getMessage());
            $this->newLine();
            if ($createDatabase) {
                $this->info('Database changes have been rolled back.');
            }
            $this->warn('Note: Files already uploaded to storage were not removed.');

            return self::FAILURE;
        }
    }

    /**
     * Count local files and folders.
     */
    protected function countLocalItems(string $path): array
    {
        $folders = 0;
        $files = 0;
        $size = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $folders++;
            } else {
                $files++;
                $size += $item->getSize();
            }
        }

        return compact('folders', 'files', 'size');
    }

    /**
     * Ensure target folder exists in storage and database.
     */
    protected function ensureTargetFolder(string $modelClass, $storage, string $target): int|string|null
    {
        $parts = array_filter(explode('/', $target));
        $parentId = null;
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath = $currentPath ? "{$currentPath}/{$part}" : $part;

            // Ensure directory exists in storage
            if (!$storage->exists($currentPath)) {
                $storage->makeDirectory($currentPath);
            }

            // Find or create in database
            $folder = $modelClass::where('name', $part)
                ->where('parent_id', $parentId)
                ->where('type', FileSystemItemType::Folder->value)
                ->first();

            if (!$folder) {
                $folder = $modelClass::create([
                    'name' => $part,
                    'type' => FileSystemItemType::Folder->value,
                    'file_type' => null,
                    'parent_id' => $parentId,
                    'size' => null,
                    'duration' => null,
                    'thumbnail' => null,
                    'storage_path' => $currentPath,
                ]);
                $this->line("  Created target folder: {$currentPath}");
                $this->foldersCreated++;
            }

            $parentId = $folder->id;
        }

        return $parentId;
    }

    /**
     * Recursively upload a directory.
     */
    protected function uploadDirectory(
        string $localPath,
        $storage,
        string $storagePath,
        ?string $modelClass,
        int|string|null $parentId
    ): void {
        $items = File::files($localPath);
        $directories = File::directories($localPath);

        // Process directories first
        foreach ($directories as $directory) {
            $name = basename($directory);

            // Skip hidden directories
            if (str_starts_with($name, '.')) {
                continue;
            }

            $newStoragePath = $storagePath ? "{$storagePath}/{$name}" : $name;

            // Create directory in storage
            if (!$storage->exists($newStoragePath)) {
                $storage->makeDirectory($newStoragePath);
            }

            $this->line("  Creating folder: {$newStoragePath}");

            $folderId = null;
            if ($modelClass) {
                $folder = $modelClass::create([
                    'name' => $name,
                    'type' => FileSystemItemType::Folder->value,
                    'file_type' => null,
                    'parent_id' => $parentId,
                    'size' => null,
                    'duration' => null,
                    'thumbnail' => null,
                    'storage_path' => $newStoragePath,
                ]);
                $folderId = $folder->id;
            }

            $this->foldersCreated++;

            // Recursively upload subdirectory
            $this->uploadDirectory($directory, $storage, $newStoragePath, $modelClass, $folderId);
        }

        // Process files
        foreach ($items as $file) {
            $name = $file->getFilename();

            // Skip hidden files
            if (str_starts_with($name, '.')) {
                continue;
            }

            $newStoragePath = $storagePath ? "{$storagePath}/{$name}" : $name;

            // Check if file already exists
            if ($storage->exists($newStoragePath)) {
                $this->warn("  Skipping (exists): {$newStoragePath}");
                $this->filesSkipped++;
                continue;
            }

            $this->line("  Uploading file: {$newStoragePath}");

            // Get file info before upload
            $size = $file->getSize();
            $mimeType = File::mimeType($file->getPathname());

            // Upload file
            $contents = File::get($file->getPathname());
            $storage->put($newStoragePath, $contents);

            $this->bytesCopied += $size;

            // Create database record
            if ($modelClass) {
                $fileType = $mimeType ? FileType::fromMimeType($mimeType)->value : FileType::Other->value;

                $modelClass::create([
                    'name' => $name,
                    'type' => FileSystemItemType::File->value,
                    'file_type' => $fileType,
                    'parent_id' => $parentId,
                    'size' => $size,
                    'duration' => null,
                    'thumbnail' => null,
                    'storage_path' => $newStoragePath,
                ]);
            }

            $this->filesUploaded++;
        }
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
