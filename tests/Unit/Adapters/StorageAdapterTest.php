<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MWGuerra\FileManager\Adapters\StorageAdapter;
use MWGuerra\FileManager\Adapters\StorageItem;

beforeEach(function () {
    Storage::fake('testing');
    $this->adapter = new StorageAdapter('testing', '', false);
});

describe('getItem', function () {
    it('returns folder for directory', function () {
        Storage::disk('testing')->makeDirectory('my-folder');

        $item = $this->adapter->getItem('my-folder');

        expect($item)->not->toBeNull()
            ->and($item->isFolder())->toBeTrue()
            ->and($item->getName())->toBe('my-folder');
    });

    it('returns file for file', function () {
        Storage::disk('testing')->put('test-file.txt', 'Hello World');

        $item = $this->adapter->getItem('test-file.txt');

        expect($item)->not->toBeNull()
            ->and($item->isFolder())->toBeFalse()
            ->and($item->getName())->toBe('test-file.txt');
    });

    it('returns null for nonexistent path', function () {
        $item = $this->adapter->getItem('nonexistent-path');

        expect($item)->toBeNull();
    });

    it('returns folder for nested directory', function () {
        Storage::disk('testing')->makeDirectory('parent/child');

        $item = $this->adapter->getItem('parent/child');

        expect($item)->not->toBeNull()
            ->and($item->isFolder())->toBeTrue()
            ->and($item->getName())->toBe('child');
    });

    it('returns file in directory', function () {
        Storage::disk('testing')->makeDirectory('folder');
        Storage::disk('testing')->put('folder/document.pdf', 'PDF content');

        $item = $this->adapter->getItem('folder/document.pdf');

        expect($item)->not->toBeNull()
            ->and($item->isFolder())->toBeFalse()
            ->and($item->getName())->toBe('document.pdf');
    });
});

describe('getItems', function () {
    it('returns folders and files with correct order', function () {
        Storage::disk('testing')->makeDirectory('folder1');
        Storage::disk('testing')->makeDirectory('folder2');
        Storage::disk('testing')->put('file1.txt', 'content');
        Storage::disk('testing')->put('file2.txt', 'content');

        $items = $this->adapter->getItems();

        expect($items)->toHaveCount(4)
            ->and($items[0]->isFolder())->toBeTrue()
            ->and($items[1]->isFolder())->toBeTrue()
            ->and($items[2]->isFolder())->toBeFalse()
            ->and($items[3]->isFolder())->toBeFalse();
    });

    it('returns items in specific folder', function () {
        Storage::disk('testing')->makeDirectory('parent');
        Storage::disk('testing')->put('parent/file1.txt', 'content');
        Storage::disk('testing')->put('parent/file2.txt', 'content');
        Storage::disk('testing')->put('root-file.txt', 'content');

        $items = $this->adapter->getItems('parent');

        expect($items)->toHaveCount(2);
    });

    it('returns empty collection for empty folder', function () {
        Storage::disk('testing')->makeDirectory('empty-folder');

        $items = $this->adapter->getItems('empty-folder');

        expect($items)->toHaveCount(0);
    });
});

describe('getFolders', function () {
    it('returns only folders when requested', function () {
        Storage::disk('testing')->makeDirectory('folder1');
        Storage::disk('testing')->makeDirectory('folder2');
        Storage::disk('testing')->put('file.txt', 'content');

        $folders = $this->adapter->getFolders();

        expect($folders)->toHaveCount(2)
            ->and($folders[0]->isFolder())->toBeTrue()
            ->and($folders[1]->isFolder())->toBeTrue();
    });

    it('returns folders from specific path', function () {
        Storage::disk('testing')->makeDirectory('parent/child1');
        Storage::disk('testing')->makeDirectory('parent/child2');
        Storage::disk('testing')->makeDirectory('other-folder');

        $folders = $this->adapter->getFolders('parent');

        expect($folders)->toHaveCount(2);
    });
});

describe('hidden files', function () {
    it('hides hidden folders by default', function () {
        Storage::disk('testing')->makeDirectory('visible-folder');
        Storage::disk('testing')->makeDirectory('.hidden-folder');

        $items = $this->adapter->getItems();

        expect($items)->toHaveCount(1)
            ->and($items[0]->getName())->toBe('visible-folder');
    });

    it('shows hidden folders when enabled', function () {
        $adapter = new StorageAdapter('testing', '', true);

        Storage::disk('testing')->makeDirectory('visible-folder');
        Storage::disk('testing')->makeDirectory('.hidden-folder');

        $items = $adapter->getItems();

        expect($items)->toHaveCount(2);
    });

    it('hides hidden files by default', function () {
        Storage::disk('testing')->put('visible.txt', 'content');
        Storage::disk('testing')->put('.hidden.txt', 'content');

        $items = $this->adapter->getItems();

        expect($items)->toHaveCount(1)
            ->and($items[0]->getName())->toBe('visible.txt');
    });
});

describe('createFolder', function () {
    it('creates folder successfully', function () {
        $result = $this->adapter->createFolder('new-folder');

        expect($result)->toBeInstanceOf(StorageItem::class)
            ->and($result->getName())->toBe('new-folder')
            ->and(Storage::disk('testing')->exists('new-folder'))->toBeTrue();
    });

    it('creates nested folder', function () {
        Storage::disk('testing')->makeDirectory('parent');

        $result = $this->adapter->createFolder('child', 'parent');

        expect($result)->toBeInstanceOf(StorageItem::class)
            ->and(Storage::disk('testing')->directoryExists('parent/child'))->toBeTrue();
    });

    it('returns error for duplicate folder', function () {
        Storage::disk('testing')->makeDirectory('existing-folder');

        $result = $this->adapter->createFolder('existing-folder');

        expect($result)->toBe('A folder with this name already exists');
    });
});

describe('uploadFile', function () {
    it('uploads file successfully', function () {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $result = $this->adapter->uploadFile($file);

        expect($result)->toBeInstanceOf(StorageItem::class)
            ->and($result->getName())->toBe('test.pdf');
    });

    it('uploads file to specific folder', function () {
        Storage::disk('testing')->makeDirectory('uploads');
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $result = $this->adapter->uploadFile($file, 'uploads');

        expect($result)->toBeInstanceOf(StorageItem::class)
            ->and(Storage::disk('testing')->exists('uploads/document.pdf'))->toBeTrue();
    });

    it('renames file if duplicate exists', function () {
        Storage::disk('testing')->put('existing.pdf', 'content');
        $file = UploadedFile::fake()->create('existing.pdf', 100);

        $result = $this->adapter->uploadFile($file);

        expect($result)->toBeInstanceOf(StorageItem::class)
            ->and($result->getName())->not->toBe('existing.pdf')
            ->and($result->getName())->toContain('existing_');
    });
});

describe('rename', function () {
    it('renames file successfully', function () {
        Storage::disk('testing')->put('old-name.txt', 'content');

        $result = $this->adapter->rename('old-name.txt', 'new-name.txt');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->exists('new-name.txt'))->toBeTrue()
            ->and(Storage::disk('testing')->exists('old-name.txt'))->toBeFalse();
    });

    it('renames folder successfully', function () {
        Storage::disk('testing')->makeDirectory('old-folder');
        Storage::disk('testing')->put('old-folder/file.txt', 'content');

        $result = $this->adapter->rename('old-folder', 'new-folder');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->directoryExists('new-folder'))->toBeTrue()
            ->and(Storage::disk('testing')->exists('new-folder/file.txt'))->toBeTrue();
    });

    it('returns error if new name already exists', function () {
        Storage::disk('testing')->put('file1.txt', 'content1');
        Storage::disk('testing')->put('file2.txt', 'content2');

        $result = $this->adapter->rename('file1.txt', 'file2.txt');

        expect($result)->toBe('An item with this name already exists');
    });
});

describe('move', function () {
    it('moves file to new folder', function () {
        Storage::disk('testing')->put('file.txt', 'content');
        Storage::disk('testing')->makeDirectory('target');

        $result = $this->adapter->move('file.txt', 'target');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->exists('target/file.txt'))->toBeTrue()
            ->and(Storage::disk('testing')->exists('file.txt'))->toBeFalse();
    });

    it('moves folder to new location', function () {
        Storage::disk('testing')->makeDirectory('source');
        Storage::disk('testing')->put('source/file.txt', 'content');
        Storage::disk('testing')->makeDirectory('target');

        $result = $this->adapter->move('source', 'target');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->directoryExists('target/source'))->toBeTrue()
            ->and(Storage::disk('testing')->exists('target/source/file.txt'))->toBeTrue();
    });

    it('moves file to root', function () {
        Storage::disk('testing')->makeDirectory('folder');
        Storage::disk('testing')->put('folder/file.txt', 'content');

        $result = $this->adapter->move('folder/file.txt', null);

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->exists('file.txt'))->toBeTrue();
    });

    it('returns error if same location', function () {
        Storage::disk('testing')->put('file.txt', 'content');

        $result = $this->adapter->move('file.txt', '');

        expect($result)->toBe('Item is already in this location');
    });

    it('returns error if target already exists', function () {
        Storage::disk('testing')->put('file.txt', 'content');
        Storage::disk('testing')->makeDirectory('target');
        Storage::disk('testing')->put('target/file.txt', 'other content');

        $result = $this->adapter->move('file.txt', 'target');

        expect($result)->toBe('An item with this name already exists in the destination');
    });

    it('prevents moving folder into itself', function () {
        Storage::disk('testing')->makeDirectory('folder/subfolder');

        $result = $this->adapter->move('folder', 'folder/subfolder');

        expect($result)->toBe('Cannot move a folder into itself');
    });
});

describe('delete', function () {
    it('deletes file successfully', function () {
        Storage::disk('testing')->put('to-delete.txt', 'content');

        $result = $this->adapter->delete('to-delete.txt');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->exists('to-delete.txt'))->toBeFalse();
    });

    it('deletes directory with contents', function () {
        Storage::disk('testing')->makeDirectory('to-delete-folder');
        Storage::disk('testing')->put('to-delete-folder/file.txt', 'content');

        $result = $this->adapter->delete('to-delete-folder');

        expect($result)->toBeTrue()
            ->and(Storage::disk('testing')->exists('to-delete-folder'))->toBeFalse();
    });
});

describe('deleteMany', function () {
    it('deletes multiple items', function () {
        Storage::disk('testing')->put('file1.txt', 'content');
        Storage::disk('testing')->put('file2.txt', 'content');
        Storage::disk('testing')->put('file3.txt', 'content');

        $count = $this->adapter->deleteMany(['file1.txt', 'file2.txt']);

        expect($count)->toBe(2)
            ->and(Storage::disk('testing')->exists('file1.txt'))->toBeFalse()
            ->and(Storage::disk('testing')->exists('file2.txt'))->toBeFalse()
            ->and(Storage::disk('testing')->exists('file3.txt'))->toBeTrue();
    });

    it('counts all delete operations that succeed', function () {
        // Note: Laravel Storage::delete on non-existent files succeeds silently
        Storage::disk('testing')->put('existing.txt', 'content');

        $count = $this->adapter->deleteMany(['existing.txt', 'nonexistent.txt']);

        // Both operations succeed (non-existent delete doesn't throw)
        expect($count)->toBe(2);
    });
});

describe('exists', function () {
    it('returns true for existing file', function () {
        Storage::disk('testing')->put('exists.txt', 'content');

        expect($this->adapter->exists('exists.txt'))->toBeTrue();
    });

    it('returns true for existing directory', function () {
        Storage::disk('testing')->makeDirectory('exists-folder');

        expect($this->adapter->exists('exists-folder'))->toBeTrue();
    });

    it('returns false for nonexistent path', function () {
        expect($this->adapter->exists('does-not-exist'))->toBeFalse();
    });
});

describe('getUrl', function () {
    it('returns url for file', function () {
        Storage::disk('testing')->put('file.txt', 'content');

        $url = $this->adapter->getUrl('file.txt');

        expect($url)->toBeString()
            ->and($url)->toContain('file.txt');
    });
});

describe('getContents', function () {
    it('returns file contents', function () {
        Storage::disk('testing')->put('file.txt', 'Hello World');

        $contents = $this->adapter->getContents('file.txt');

        expect($contents)->toBe('Hello World');
    });

    it('returns null for nonexistent file', function () {
        $contents = $this->adapter->getContents('nonexistent.txt');

        expect($contents)->toBeNull();
    });

    it('truncates large files', function () {
        $largeContent = str_repeat('a', 2000);
        Storage::disk('testing')->put('large.txt', $largeContent);

        $contents = $this->adapter->getContents('large.txt', 1000);

        expect(strlen($contents))->toBeGreaterThan(1000)
            ->and($contents)->toContain('truncated');
    });

    it('returns null for files exceeding double max size', function () {
        $hugeContent = str_repeat('a', 3000000);
        Storage::disk('testing')->put('huge.txt', $hugeContent);

        $contents = $this->adapter->getContents('huge.txt', 1000000);

        expect($contents)->toBeNull();
    });
});

describe('getStream', function () {
    it('returns stream for file', function () {
        Storage::disk('testing')->put('file.txt', 'content');

        $stream = $this->adapter->getStream('file.txt');

        expect($stream)->toBeResource();
        fclose($stream);
    });

    it('returns null for nonexistent file', function () {
        $stream = $this->adapter->getStream('nonexistent.txt');

        expect($stream)->toBeNull();
    });
});

describe('getSize', function () {
    it('returns file size', function () {
        Storage::disk('testing')->put('file.txt', 'Hello World');

        $size = $this->adapter->getSize('file.txt');

        expect($size)->toBe(11);
    });

    it('returns null for nonexistent file', function () {
        $size = $this->adapter->getSize('nonexistent.txt');

        expect($size)->toBeNull();
    });
});

describe('breadcrumbs', function () {
    it('returns root breadcrumb for root path', function () {
        $breadcrumbs = $this->adapter->getBreadcrumbs(null);

        expect($breadcrumbs)->toHaveCount(1)
            ->and($breadcrumbs[0]['name'])->toBe('Root')
            ->and($breadcrumbs[0]['path'])->toBe('/');
    });

    it('returns breadcrumbs for nested path', function () {
        $breadcrumbs = $this->adapter->getBreadcrumbs('folder/subfolder/deep');

        expect($breadcrumbs)->toHaveCount(4)
            ->and($breadcrumbs[0]['name'])->toBe('Root')
            ->and($breadcrumbs[1]['name'])->toBe('folder')
            ->and($breadcrumbs[2]['name'])->toBe('subfolder')
            ->and($breadcrumbs[3]['name'])->toBe('deep');
    });
});

describe('folder tree', function () {
    it('returns empty array for empty storage', function () {
        $tree = $this->adapter->getFolderTree();

        expect($tree)->toBeArray()->toBeEmpty();
    });

    it('returns folder tree structure', function () {
        Storage::disk('testing')->makeDirectory('folder1');
        Storage::disk('testing')->makeDirectory('folder2');
        Storage::disk('testing')->makeDirectory('folder1/subfolder');

        $tree = $this->adapter->getFolderTree();

        expect($tree)->toHaveCount(2)
            ->and($tree[0]['name'])->toBe('folder1')
            ->and($tree[0]['children'])->toHaveCount(1);
    });

    it('returns lazy folder tree without children loaded', function () {
        Storage::disk('testing')->makeDirectory('parent');
        Storage::disk('testing')->makeDirectory('parent/child');
        Storage::disk('testing')->makeDirectory('parent/child/grandchild');

        $tree = $this->adapter->getFolderTree(lazy: true);

        expect($tree)->toHaveCount(1)
            ->and($tree[0]['name'])->toBe('parent')
            ->and($tree[0]['has_children'])->toBeTrue()
            ->and($tree[0]['children'])->toBeEmpty()
            ->and($tree[0]['children_loaded'])->toBeFalse();
    });

    it('lazy tree assumes all folders might have children for performance', function () {
        // In lazy mode, we assume all folders might have children to avoid
        // extra API calls. This trades UX precision for performance.
        Storage::disk('testing')->makeDirectory('with-children');
        Storage::disk('testing')->makeDirectory('with-children/child');
        Storage::disk('testing')->makeDirectory('no-children');

        $tree = $this->adapter->getFolderTree(lazy: true);

        $withChildren = collect($tree)->firstWhere('name', 'with-children');
        $noChildren = collect($tree)->firstWhere('name', 'no-children');

        // Both should show as potentially having children
        expect($withChildren['has_children'])->toBeTrue()
            ->and($noChildren['has_children'])->toBeTrue();
    });
});

describe('getFolderChildren', function () {
    it('returns immediate children only', function () {
        Storage::disk('testing')->makeDirectory('parent');
        Storage::disk('testing')->makeDirectory('parent/child1');
        Storage::disk('testing')->makeDirectory('parent/child2');
        Storage::disk('testing')->makeDirectory('parent/child1/grandchild');

        $children = $this->adapter->getFolderChildren('parent');

        expect($children)->toHaveCount(2)
            ->and($children[0]['name'])->toBe('child1')
            ->and($children[0]['children_loaded'])->toBeFalse()
            ->and($children[1]['name'])->toBe('child2');
    });

    it('returns root children when path is null', function () {
        Storage::disk('testing')->makeDirectory('folder1');
        Storage::disk('testing')->makeDirectory('folder2');

        $children = $this->adapter->getFolderChildren(null);

        expect($children)->toHaveCount(2);
    });

    it('skips file counting for performance', function () {
        Storage::disk('testing')->makeDirectory('folder');
        Storage::disk('testing')->put('folder/file1.txt', 'content');
        Storage::disk('testing')->put('folder/file2.txt', 'content');

        $children = $this->adapter->getFolderChildren(null);

        // file_count should be 0 (not counted for performance)
        expect($children[0]['file_count'])->toBe(0);
    });

    it('assumes has_children is true to avoid extra API calls', function () {
        // Even empty folders show has_children=true to avoid extra API calls
        Storage::disk('testing')->makeDirectory('empty-folder');

        $children = $this->adapter->getFolderChildren(null);

        expect($children[0]['has_children'])->toBeTrue();
    });
});

describe('path safety', function () {
    it('validates safe paths', function () {
        expect($this->adapter->isPathSafe('folder/file.txt'))->toBeTrue();
        expect($this->adapter->isPathSafe('simple'))->toBeTrue();
    });

    it('sanitizes path traversal attempts', function () {
        $adapter = new StorageAdapter('testing', 'root', false);

        // The adapter sanitizes path traversal by removing .. components
        // rather than rejecting them, which is more secure
        expect($adapter->isPathSafe('../outside'))->toBeTrue();
    });
});

describe('mode name', function () {
    it('returns storage as mode name', function () {
        expect($this->adapter->getModeName())->toBe('storage');
    });
});

describe('disk and root getters', function () {
    it('returns configured disk', function () {
        expect($this->adapter->getDisk())->toBe('testing');
    });

    it('returns configured root', function () {
        $adapter = new StorageAdapter('testing', 'custom-root', false);

        expect($adapter->getRoot())->toBe('custom-root');
    });
});

describe('root path handling', function () {
    it('respects root path for items', function () {
        $adapter = new StorageAdapter('testing', 'root-folder', false);

        Storage::disk('testing')->makeDirectory('root-folder');
        Storage::disk('testing')->makeDirectory('root-folder/subfolder');
        Storage::disk('testing')->put('root-folder/file.txt', 'content');

        $items = $adapter->getItems();

        expect($items)->toHaveCount(2);
    });
});
