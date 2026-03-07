<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Adapters\DatabaseAdapter;
use MWGuerra\FileManager\Adapters\DatabaseItem;
use MWGuerra\FileManager\Tests\Fixtures\UuidFileSystemItem;

beforeEach(function () {
    // Create UUID table for testing
    Schema::create('uuid_file_system_items', function ($table) {
        $table->uuid('id')->primary();
        $table->uuid('parent_id')->nullable();
        $table->string('name');
        $table->string('type');
        $table->string('file_type')->nullable();
        $table->unsignedBigInteger('size')->nullable();
        $table->unsignedInteger('duration')->nullable();
        $table->string('thumbnail')->nullable();
        $table->string('storage_path')->nullable();
        $table->timestamps();

        $table->index('type');
        $table->index('file_type');
        $table->unique(['parent_id', 'name']);

        $table->foreign('parent_id')
            ->references('id')
            ->on('uuid_file_system_items')
            ->cascadeOnDelete();
    });

    Storage::fake('testing');
    $this->adapter = new DatabaseAdapter(UuidFileSystemItem::class, 'testing', 'uploads');
});

afterEach(function () {
    Schema::dropIfExists('uuid_file_system_items');
});

describe('UUID Support - getItems', function () {
    it('returns items from root folder with UUID ids', function () {
        $folder1 = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder1']);
        $folder2 = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder2']);
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        // Verify UUIDs are being used
        expect($folder1->id)->toBeString()
            ->and(strlen($folder1->id))->toBe(36);

        $items = $this->adapter->getItems();

        expect($items)->toHaveCount(3);
    });

    it('returns items from specific folder using UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        UuidFileSystemItem::factory()->file()->create(['name' => 'child.pdf', 'parent_id' => $folder->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'root.pdf']);

        // Using UUID string
        $items = $this->adapter->getItems($folder->id);

        expect($items)->toHaveCount(1)
            ->and($items->first()->getName())->toBe('child.pdf');
    });

    it('returns empty collection for empty folder with UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'empty']);

        $items = $this->adapter->getItems($folder->id);

        expect($items)->toHaveCount(0);
    });
});

describe('UUID Support - getFolders', function () {
    it('returns only folders from root with UUID ids', function () {
        UuidFileSystemItem::factory()->folder()->create(['name' => 'folder1']);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'folder2']);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        $folders = $this->adapter->getFolders();

        expect($folders)->toHaveCount(2)
            ->and($folders->every(fn ($item) => $item->isFolder()))->toBeTrue();
    });

    it('returns folders from specific parent using UUID', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'child1', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'child2', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'other']);

        $folders = $this->adapter->getFolders($parent->id);

        expect($folders)->toHaveCount(2);
    });
});

describe('UUID Support - getItem', function () {
    it('returns item by UUID', function () {
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'document.pdf']);

        // Verify UUID format
        expect($file->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

        $item = $this->adapter->getItem($file->id);

        expect($item)->not->toBeNull()
            ->and($item)->toBeInstanceOf(DatabaseItem::class)
            ->and($item->getName())->toBe('document.pdf');
    });

    it('returns null for nonexistent UUID', function () {
        $item = $this->adapter->getItem('00000000-0000-0000-0000-000000000000');

        expect($item)->toBeNull();
    });

    it('returns null for invalid UUID format', function () {
        $item = $this->adapter->getItem('invalid-uuid');

        expect($item)->toBeNull();
    });
});

describe('UUID Support - createFolder', function () {
    it('creates folder in root with UUID', function () {
        $result = $this->adapter->createFolder('new-folder');

        expect($result)->toBeInstanceOf(DatabaseItem::class)
            ->and($result->getName())->toBe('new-folder')
            ->and($result->isFolder())->toBeTrue()
            ->and($result->getIdentifier())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

        $this->assertDatabaseHas('uuid_file_system_items', [
            'name' => 'new-folder',
            'type' => 'folder',
            'parent_id' => null,
        ]);
    });

    it('creates folder in parent folder using UUID', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);

        $result = $this->adapter->createFolder('child', $parent->id);

        expect($result)->toBeInstanceOf(DatabaseItem::class)
            ->and($result->getName())->toBe('child');

        $this->assertDatabaseHas('uuid_file_system_items', [
            'name' => 'child',
            'type' => 'folder',
            'parent_id' => $parent->id,
        ]);
    });

    it('returns error for duplicate folder name with UUID', function () {
        UuidFileSystemItem::factory()->folder()->create(['name' => 'existing']);

        $result = $this->adapter->createFolder('existing');

        expect($result)->toBe('A folder with this name already exists');
    });
});

describe('UUID Support - uploadFile', function () {
    it('uploads file to root with UUID', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $result = $this->adapter->uploadFile($file);

        expect($result)->toBeInstanceOf(DatabaseItem::class)
            ->and($result->getName())->toBe('document.pdf')
            ->and($result->isFile())->toBeTrue()
            ->and($result->getIdentifier())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

        $this->assertDatabaseHas('uuid_file_system_items', [
            'name' => 'document.pdf',
            'type' => 'file',
            'parent_id' => null,
        ]);
    });

    it('uploads file to specific folder using UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'uploads']);
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $result = $this->adapter->uploadFile($file, $folder->id);

        expect($result)->toBeInstanceOf(DatabaseItem::class);

        $this->assertDatabaseHas('uuid_file_system_items', [
            'name' => 'document.pdf',
            'parent_id' => $folder->id,
        ]);
    });

    it('renames file on duplicate with UUID', function () {
        UuidFileSystemItem::factory()->file()->create(['name' => 'existing.pdf']);
        $file = UploadedFile::fake()->create('existing.pdf', 100, 'application/pdf');

        $result = $this->adapter->uploadFile($file);

        expect($result)->toBeInstanceOf(DatabaseItem::class)
            ->and($result->getName())->not->toBe('existing.pdf')
            ->and($result->getName())->toContain('existing_');
    });
});

describe('UUID Support - rename', function () {
    it('renames item successfully using UUID', function () {
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'old-name.pdf']);

        $result = $this->adapter->rename($file->id, 'new-name.pdf');

        expect($result)->toBeTrue();

        $this->assertDatabaseHas('uuid_file_system_items', [
            'id' => $file->id,
            'name' => 'new-name.pdf',
        ]);
    });

    it('returns error for nonexistent UUID', function () {
        $result = $this->adapter->rename('00000000-0000-0000-0000-000000000000', 'new-name.pdf');

        expect($result)->toBe('Item not found');
    });

    it('returns error for duplicate name with UUID', function () {
        UuidFileSystemItem::factory()->file()->create(['name' => 'existing.pdf']);
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'other.pdf']);

        $result = $this->adapter->rename($file->id, 'existing.pdf');

        expect($result)->toBe('An item with this name already exists in this folder');
    });
});

describe('UUID Support - move', function () {
    it('moves item to another folder using UUID', function () {
        $target = UuidFileSystemItem::factory()->folder()->create(['name' => 'target']);
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        $result = $this->adapter->move($file->id, $target->id);

        expect($result)->toBeTrue();

        $this->assertDatabaseHas('uuid_file_system_items', [
            'id' => $file->id,
            'parent_id' => $target->id,
        ]);
    });

    it('moves item to root using UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder']);
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf', 'parent_id' => $folder->id]);

        $result = $this->adapter->move($file->id, null);

        expect($result)->toBeTrue();

        $this->assertDatabaseHas('uuid_file_system_items', [
            'id' => $file->id,
            'parent_id' => null,
        ]);
    });

    it('returns error for nonexistent UUID', function () {
        $result = $this->adapter->move('00000000-0000-0000-0000-000000000000', null);

        expect($result)->toBe('Item not found');
    });

    it('returns error when moving to same location with UUID', function () {
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        $result = $this->adapter->move($file->id, null);

        expect($result)->toBe('Item is already in this folder');
    });

    it('returns error for duplicate name in target with UUID', function () {
        $target = UuidFileSystemItem::factory()->folder()->create(['name' => 'target']);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf', 'parent_id' => $target->id]);
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        $result = $this->adapter->move($file->id, $target->id);

        expect($result)->toBe('An item with this name already exists in the destination folder');
    });

    it('prevents moving folder into itself with UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        $child = UuidFileSystemItem::factory()->folder()->create(['name' => 'child', 'parent_id' => $folder->id]);

        $result = $this->adapter->move($folder->id, $child->id);

        expect($result)->toBe('Cannot move a folder into itself or its descendants');
    });
});

describe('UUID Support - delete', function () {
    it('deletes file successfully using UUID', function () {
        Storage::disk('testing')->put('uploads/file.pdf', 'content');
        $file = UuidFileSystemItem::factory()->file()->create([
            'name' => 'file.pdf',
            'storage_path' => 'uploads/file.pdf',
        ]);

        $result = $this->adapter->delete($file->id);

        expect($result)->toBeTrue();

        $this->assertDatabaseMissing('uuid_file_system_items', ['id' => $file->id]);
    });

    it('deletes folder successfully using UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder']);

        $result = $this->adapter->delete($folder->id);

        expect($result)->toBeTrue();

        $this->assertDatabaseMissing('uuid_file_system_items', ['id' => $folder->id]);
    });

    it('returns error for nonexistent UUID', function () {
        $result = $this->adapter->delete('00000000-0000-0000-0000-000000000000');

        expect($result)->toBe('Item not found');
    });
});

describe('UUID Support - deleteMany', function () {
    it('deletes multiple items using UUIDs', function () {
        $file1 = UuidFileSystemItem::factory()->file()->create(['name' => 'file1.pdf']);
        $file2 = UuidFileSystemItem::factory()->file()->create(['name' => 'file2.pdf']);
        $file3 = UuidFileSystemItem::factory()->file()->create(['name' => 'file3.pdf']);

        $count = $this->adapter->deleteMany([$file1->id, $file2->id]);

        expect($count)->toBe(2);

        $this->assertDatabaseMissing('uuid_file_system_items', ['id' => $file1->id]);
        $this->assertDatabaseMissing('uuid_file_system_items', ['id' => $file2->id]);
        $this->assertDatabaseHas('uuid_file_system_items', ['id' => $file3->id]);
    });
});

describe('UUID Support - exists', function () {
    it('returns true for existing UUID', function () {
        $file = UuidFileSystemItem::factory()->file()->create(['name' => 'file.pdf']);

        expect($this->adapter->exists($file->id))->toBeTrue();
    });

    it('returns false for nonexistent UUID', function () {
        expect($this->adapter->exists('00000000-0000-0000-0000-000000000000'))->toBeFalse();
    });
});

describe('UUID Support - getUrl', function () {
    it('returns url for file with storage path using UUID', function () {
        Storage::disk('testing')->put('uploads/file.pdf', 'content');
        $file = UuidFileSystemItem::factory()->file()->create([
            'name' => 'file.pdf',
            'storage_path' => 'uploads/file.pdf',
        ]);

        $url = $this->adapter->getUrl($file->id);

        expect($url)->toBeString()
            ->and($url)->toContain('file.pdf');
    });

    it('returns null for nonexistent UUID', function () {
        $url = $this->adapter->getUrl('00000000-0000-0000-0000-000000000000');

        expect($url)->toBeNull();
    });

    it('returns null for folder using UUID', function () {
        $folder = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder']);

        $url = $this->adapter->getUrl($folder->id);

        expect($url)->toBeNull();
    });
});

describe('UUID Support - getContents', function () {
    it('returns file contents using UUID', function () {
        Storage::disk('testing')->put('uploads/file.txt', 'Hello World');
        $file = UuidFileSystemItem::factory()->file()->create([
            'name' => 'file.txt',
            'storage_path' => 'uploads/file.txt',
        ]);

        $contents = $this->adapter->getContents($file->id);

        expect($contents)->toBe('Hello World');
    });

    it('returns null for nonexistent UUID', function () {
        $contents = $this->adapter->getContents('00000000-0000-0000-0000-000000000000');

        expect($contents)->toBeNull();
    });
});

describe('UUID Support - getStream', function () {
    it('returns stream for file using UUID', function () {
        Storage::disk('testing')->put('uploads/file.txt', 'content');
        $file = UuidFileSystemItem::factory()->file()->create([
            'name' => 'file.txt',
            'storage_path' => 'uploads/file.txt',
        ]);

        $stream = $this->adapter->getStream($file->id);

        expect($stream)->toBeResource();
        fclose($stream);
    });

    it('returns null for nonexistent UUID', function () {
        $stream = $this->adapter->getStream('00000000-0000-0000-0000-000000000000');

        expect($stream)->toBeNull();
    });
});

describe('UUID Support - getSize', function () {
    it('returns stored size using UUID', function () {
        $file = UuidFileSystemItem::factory()->file()->create([
            'name' => 'file.pdf',
            'size' => 12345,
        ]);

        $size = $this->adapter->getSize($file->id);

        expect($size)->toBe(12345);
    });

    it('returns null for nonexistent UUID', function () {
        $size = $this->adapter->getSize('00000000-0000-0000-0000-000000000000');

        expect($size)->toBeNull();
    });
});

describe('UUID Support - breadcrumbs', function () {
    it('returns root breadcrumb for root path', function () {
        $breadcrumbs = $this->adapter->getBreadcrumbs(null);

        expect($breadcrumbs)->toHaveCount(1)
            ->and($breadcrumbs[0]['name'])->toBe('Root');
    });

    it('returns breadcrumbs for nested folder using UUIDs', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        $child = UuidFileSystemItem::factory()->folder()->create(['name' => 'child', 'parent_id' => $parent->id]);

        $breadcrumbs = $this->adapter->getBreadcrumbs($child->id);

        expect($breadcrumbs)->toHaveCount(3)
            ->and($breadcrumbs[0]['name'])->toBe('Root')
            ->and($breadcrumbs[1]['name'])->toBe('parent')
            ->and($breadcrumbs[2]['name'])->toBe('child');
    });
});

describe('UUID Support - folder tree', function () {
    it('returns folder tree structure with UUIDs', function () {
        $folder1 = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder1']);
        $folder2 = UuidFileSystemItem::factory()->folder()->create(['name' => 'folder2']);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'child', 'parent_id' => $folder1->id]);

        $tree = $this->adapter->getFolderTree();

        expect($tree)->toHaveCount(2);
    });
});

describe('UUID Support - model methods', function () {
    it('getFolderTree works with UUID parent_id', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'child1', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'child2', 'parent_id' => $parent->id]);

        $tree = UuidFileSystemItem::getFolderTree($parent->id);

        expect($tree)->toHaveCount(2)
            ->and($tree[0]['name'])->toBe('child1')
            ->and($tree[1]['name'])->toBe('child2');
    });

    it('getItemsInFolder works with UUID parent_id', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file1.pdf', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file2.pdf', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'other.pdf']);

        $items = UuidFileSystemItem::getItemsInFolder($parent->id);

        expect($items)->toHaveCount(2);
    });

    it('getDirectFileCountForFolder works with UUID folder_id', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file1.pdf', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file2.pdf', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->folder()->create(['name' => 'subfolder', 'parent_id' => $parent->id]);

        $count = UuidFileSystemItem::getDirectFileCountForFolder($parent->id);

        expect($count)->toBe(2);
    });

    it('getFileCountForFolder works with UUID folder_id', function () {
        $parent = UuidFileSystemItem::factory()->folder()->create(['name' => 'parent']);
        $child = UuidFileSystemItem::factory()->folder()->create(['name' => 'child', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file1.pdf', 'parent_id' => $parent->id]);
        UuidFileSystemItem::factory()->file()->create(['name' => 'file2.pdf', 'parent_id' => $child->id]);

        $count = UuidFileSystemItem::getFileCountForFolder($parent->id);

        expect($count)->toBe(2);
    });
});
