<?php

use MWGuerra\FileManager\Traits\DetectsS3TempUploads;

// Create a test class that uses the trait
class TestDetectsS3TempUploads
{
    use DetectsS3TempUploads;
}

describe('DetectsS3TempUploads trait', function () {
    beforeEach(function () {
        $this->testClass = new TestDetectsS3TempUploads;
    });

    it('detects S3 temp disk when livewire uses s3 driver', function () {
        // Configure Livewire to use S3
        config()->set('livewire.temporary_file_upload.disk', 's3-test');
        config()->set('filesystems.disks.s3-test', [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'us-east-1',
            'bucket' => 'test-bucket',
        ]);

        expect($this->testClass->isS3TempDisk())->toBeTrue();
        expect($this->testClass->supportsMultipleUploads())->toBeFalse();
    });

    it('detects minio temp disk as S3-compatible', function () {
        // Configure Livewire to use MinIO
        config()->set('livewire.temporary_file_upload.disk', 'minio-test');
        config()->set('filesystems.disks.minio-test', [
            'driver' => 'minio',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'us-east-1',
            'bucket' => 'test-bucket',
        ]);

        expect($this->testClass->isS3TempDisk())->toBeTrue();
        expect($this->testClass->supportsMultipleUploads())->toBeFalse();
    });

    it('returns false for local disk', function () {
        config()->set('livewire.temporary_file_upload.disk', 'local');
        config()->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);

        expect($this->testClass->isS3TempDisk())->toBeFalse();
        expect($this->testClass->supportsMultipleUploads())->toBeTrue();
    });

    it('returns false for public disk', function () {
        config()->set('livewire.temporary_file_upload.disk', 'public');
        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ]);

        expect($this->testClass->isS3TempDisk())->toBeFalse();
        expect($this->testClass->supportsMultipleUploads())->toBeTrue();
    });

    it('returns false when no temp disk is configured', function () {
        config()->set('livewire.temporary_file_upload.disk', null);

        expect($this->testClass->isS3TempDisk())->toBeFalse();
        expect($this->testClass->supportsMultipleUploads())->toBeTrue();
    });

    it('returns false when temp disk does not exist in filesystems', function () {
        config()->set('livewire.temporary_file_upload.disk', 'non-existent-disk');

        expect($this->testClass->isS3TempDisk())->toBeFalse();
        expect($this->testClass->supportsMultipleUploads())->toBeTrue();
    });

    it('provides a user-friendly disabled message', function () {
        $message = $this->testClass->getMultipleUploadsDisabledMessage();

        expect($message)->toBeString();
        expect($message)->toContain('S3');
        expect($message)->toContain('one at a time');
    });
});
