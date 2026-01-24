<?php

namespace MWGuerra\FileManager\Traits;

/**
 * Detects when Livewire's temporary file upload disk is S3.
 *
 * Livewire doesn't support multiple file uploads when the temp disk is S3.
 * This trait provides methods to detect this configuration and adjust the UI accordingly.
 */
trait DetectsS3TempUploads
{
    /**
     * Check if Livewire's temporary file upload disk is S3-based.
     *
     * When true, multiple file uploads are not supported and the UI should
     * disable the 'multiple' attribute on file inputs.
     */
    public function isS3TempDisk(): bool
    {
        $tempDisk = config('livewire.temporary_file_upload.disk');

        if (empty($tempDisk)) {
            return false;
        }

        $diskConfig = config("filesystems.disks.{$tempDisk}");

        if (empty($diskConfig)) {
            return false;
        }

        // Check if the disk driver is S3 or S3-compatible
        $driver = $diskConfig['driver'] ?? '';

        return in_array($driver, ['s3', 'minio']);
    }

    /**
     * Check if multiple file uploads are supported.
     *
     * Returns false when Livewire's temp disk is S3-based.
     */
    public function supportsMultipleUploads(): bool
    {
        return ! $this->isS3TempDisk();
    }

    /**
     * Get a user-friendly message explaining why multiple uploads are disabled.
     */
    public function getMultipleUploadsDisabledMessage(): string
    {
        return 'Multiple file uploads are disabled because your server uses S3 for temporary file storage. Please upload files one at a time.';
    }
}
