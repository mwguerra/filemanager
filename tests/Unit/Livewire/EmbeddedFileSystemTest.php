<?php

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use MWGuerra\FileManager\Livewire\EmbeddedFileSystem;
use MWGuerra\FileManager\Models\FileSystemItem;

beforeEach(function () {
    $this->testPath = sys_get_temp_dir() . '/filemanager-embedded-unit-' . uniqid();
    mkdir($this->testPath, 0777, true);

    $this->app['config']->set('filesystems.disks.testing', [
        'driver' => 'local',
        'root' => $this->testPath,
    ]);

    $this->app['config']->set('filemanager.mode', 'storage');
    $this->app['config']->set('filemanager.model', FileSystemItem::class);
    $this->app['config']->set('filemanager.storage_mode.disk', 'testing');

    // Create test folder structure
    mkdir($this->testPath . '/documents', 0777, true);
    mkdir($this->testPath . '/images', 0777, true);
    file_put_contents($this->testPath . '/readme.txt', 'readme content');
    file_put_contents($this->testPath . '/documents/contract.pdf', 'pdf content');
});

afterEach(function () {
    if (isset($this->testPath) && is_dir($this->testPath)) {
        deleteDirectory($this->testPath);
    }
});

describe('mount', function () {
    it('mounts with default configuration', function () {
        $component = new EmbeddedFileSystem();
        $component->mount();

        expect($component->height)->toBe('500px');
        expect($component->showHeader)->toBeTrue();
        expect($component->showSidebar)->toBeTrue();
        expect($component->defaultViewMode)->toBe('grid');
        expect($component->viewMode)->toBe('grid');
        expect($component->currentPath)->toBeNull();
    });

    it('mounts with custom configuration', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(
            height: '600px',
            showHeader: false,
            showSidebar: false,
            defaultViewMode: 'list',
            disk: 'testing',
            target: 'uploads',
            initialFolder: 'documents'
        );

        expect($component->height)->toBe('600px');
        expect($component->showHeader)->toBeFalse();
        expect($component->showSidebar)->toBeFalse();
        expect($component->defaultViewMode)->toBe('list');
        expect($component->disk)->toBe('testing');
        expect($component->target)->toBe('uploads');
        expect($component->currentPath)->toBe('documents');
    });
});

describe('mode', function () {
    it('returns storage mode', function () {
        $component = new EmbeddedFileSystem();
        $component->mount();

        expect($component->getMode())->toBe('storage');
    });

    it('is always read-only', function () {
        $component = new EmbeddedFileSystem();
        $component->mount();

        expect($component->isReadOnly())->toBeTrue();
    });
});

describe('navigation', function () {
    it('navigates to a folder', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->navigateTo('documents');

        expect($component->currentPath)->toBe('documents');
        expect($component->selectedItems)->toBe([]);
    });

    it('navigates to root', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->currentPath = 'documents';

        $component->navigateTo(null);

        expect($component->currentPath)->toBeNull();
    });
});

describe('folder expansion', function () {
    it('toggles folder expansion', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->expandedFolders)->toBe(['root']);

        $component->toggleFolder('documents');
        expect($component->expandedFolders)->toContain('documents');

        $component->toggleFolder('documents');
        expect($component->expandedFolders)->not->toContain('documents');
    });

    it('checks if folder is expanded', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->isFolderExpanded(null))->toBeTrue();
        expect($component->isFolderExpanded('documents'))->toBeFalse();

        $component->toggleFolder('documents');

        expect($component->isFolderExpanded('documents'))->toBeTrue();
    });
});

describe('view mode', function () {
    it('sets view mode to list', function () {
        $component = new EmbeddedFileSystem();
        $component->mount();

        expect($component->viewMode)->toBe('grid');

        $component->setViewMode('list');

        expect($component->viewMode)->toBe('list');
    });

    it('sets view mode to grid', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(defaultViewMode: 'list');

        expect($component->viewMode)->toBe('list');

        $component->setViewMode('grid');

        expect($component->viewMode)->toBe('grid');
    });
});

describe('selection', function () {
    it('toggles single item selection', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->toggleSelection('readme.txt');
        expect($component->selectedItems)->toBe(['readme.txt']);

        $component->toggleSelection('readme.txt');
        expect($component->selectedItems)->toBe([]);
    });

    it('toggles multi-selection', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->toggleSelection('readme.txt', true);
        $component->toggleSelection('documents', true);

        expect($component->selectedItems)->toBe(['readme.txt', 'documents']);
    });

    it('clears selection', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->selectedItems = ['readme.txt'];

        $component->clearSelection();

        expect($component->selectedItems)->toBe([]);
    });

    it('checks if item is selected', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->toggleSelection('readme.txt');

        expect($component->isSelected('readme.txt'))->toBeTrue();
        expect($component->isSelected('documents'))->toBeFalse();
    });
});

describe('read-only behavior', function () {
    it('does not record changes in read-only mode', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        // Since it's read-only, the component shouldn't have any changes
        expect($component->getChanges())->toHaveCount(0);
    });
});

describe('items property', function () {
    it('returns items collection from adapter', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $items = $component->items;

        expect($items)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        // Should contain: documents folder, images folder, readme.txt
        expect($items->count())->toBeGreaterThanOrEqual(3);
    });

    it('returns items for subfolder', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->navigateTo('documents');

        $items = $component->items;

        expect($items)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        // Should contain: contract.pdf
        expect($items->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('folder tree property', function () {
    it('returns folder tree array', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $folderTree = $component->folderTree;

        expect($folderTree)->toBeArray();
    });
});

describe('breadcrumbs property', function () {
    it('returns breadcrumbs for root', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $breadcrumbs = $component->breadcrumbs;

        expect($breadcrumbs)->toBeArray();
    });

    it('returns breadcrumbs for subfolder', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->navigateTo('documents');

        $breadcrumbs = $component->breadcrumbs;

        expect($breadcrumbs)->toBeArray();
        expect(count($breadcrumbs))->toBeGreaterThan(0);
    });
});

describe('select all', function () {
    it('selects all items in current view', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->selectAll();

        $itemCount = $component->items->count();
        expect(count($component->selectedItems))->toBe($itemCount);
    });
});

describe('all selected', function () {
    it('returns true when all items are selected', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->selectAll();

        expect($component->allSelected())->toBeTrue();
    });

    it('returns false when not all items are selected', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        // Select only one item
        $component->toggleSelection('readme.txt');

        expect($component->allSelected())->toBeFalse();
    });

    it('returns false when no items exist', function () {
        // Create empty folder
        mkdir($this->testPath . '/empty', 0777, true);

        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->navigateTo('empty');

        expect($component->allSelected())->toBeFalse();
    });
});

describe('handle item click', function () {
    it('navigates to folder when clicking folder', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->handleItemClick('documents');

        expect($component->currentPath)->toBe('documents');
        expect($component->expandedFolders)->toContain('documents');
    });

    it('opens preview when clicking file', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->handleItemClick('readme.txt');

        expect($component->previewItemId)->toBe('readme.txt');
    });
});

describe('preview', function () {
    it('opens preview for file', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->openPreview('readme.txt');

        expect($component->previewItemId)->toBe('readme.txt');
    });

    it('does not open preview for folder', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->openPreview('documents');

        expect($component->previewItemId)->toBeNull();
    });

    it('closes preview', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'readme.txt';

        $component->closePreview();

        expect($component->previewItemId)->toBeNull();
    });

    it('returns preview item property', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'readme.txt';

        $previewItem = $component->previewItem;

        expect($previewItem)->not->toBeNull();
        expect($previewItem->getName())->toBe('readme.txt');
    });

    it('returns null preview item when no item selected', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->previewItem)->toBeNull();
    });
});

describe('preview url', function () {
    it('returns url for preview item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'readme.txt';

        $url = $component->getPreviewUrl();

        expect($url)->not->toBeNull();
    });

    it('returns null when no preview item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->getPreviewUrl())->toBeNull();
    });
});

describe('text content', function () {
    it('returns text content for text file', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'readme.txt';

        $content = $component->getTextContent();

        expect($content)->toBe('readme content');
    });

    it('returns null for non-text file', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'documents/contract.pdf';

        $content = $component->getTextContent();

        expect($content)->toBeNull();
    });

    it('returns null when no preview item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->getTextContent())->toBeNull();
    });
});

describe('all folders property', function () {
    it('returns collection of folders', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $allFolders = $component->allFolders;

        expect($allFolders)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($allFolders->count())->toBeGreaterThanOrEqual(2);
    });
});

describe('root file count property', function () {
    it('returns count of files in root', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $count = $component->rootFileCount;

        expect($count)->toBeGreaterThanOrEqual(1);
    });
});

describe('changes count property', function () {
    it('returns zero for read-only component', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->changesCount)->toBe(0);
    });
});

describe('move dialog', function () {
    it('opens move dialog for single item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->openMoveDialog('readme.txt');

        expect($component->itemToMoveId)->toBe('readme.txt');
        expect($component->itemsToMove)->toBe([]);
        expect($component->moveTargetPath)->toBeNull();
    });

    it('opens move dialog for selected items', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->selectedItems = ['readme.txt', 'documents'];

        $component->openMoveDialogForSelected();

        expect($component->itemsToMove)->toBe(['readme.txt', 'documents']);
        expect($component->itemToMoveId)->toBeNull();
    });

    it('does not open move dialog when no items selected', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->selectedItems = [];

        $component->openMoveDialogForSelected();

        expect($component->itemsToMove)->toBe([]);
    });
});

describe('rename dialog', function () {
    it('opens rename dialog', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->openRenameDialog('readme.txt');

        expect($component->itemToRenameId)->toBe('readme.txt');
        expect($component->renameItemName)->toBe('readme.txt');
    });
});

describe('subfolder dialog', function () {
    it('opens subfolder dialog', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $component->openCreateSubfolderDialog('documents');

        expect($component->subfolderParentPath)->toBe('documents');
        expect($component->subfolderName)->toBe('');
    });
});

describe('computed item properties', function () {
    it('returns item to move property', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->itemToMoveId = 'readme.txt';

        $item = $component->itemToMove;

        expect($item)->not->toBeNull();
        expect($item->getName())->toBe('readme.txt');
    });

    it('returns null item to move when not set', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->itemToMove)->toBeNull();
    });

    it('returns subfolder parent property', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->subfolderParentPath = 'documents';

        $parent = $component->subfolderParent;

        expect($parent)->not->toBeNull();
        expect($parent->getName())->toBe('documents');
    });

    it('returns item to rename property', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->itemToRenameId = 'readme.txt';

        $item = $component->itemToRename;

        expect($item)->not->toBeNull();
        expect($item->getName())->toBe('readme.txt');
    });
});

describe('file type', function () {
    it('returns file type for item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        $items = $component->items;
        $file = $items->first(fn ($item) => !$item->isFolder());

        if ($file) {
            $fileType = $component->getFileTypeForItem($file);

            expect($fileType)->toBeInstanceOf(\MWGuerra\FileManager\Contracts\FileTypeContract::class);
        }
    });
});

describe('preview file type property', function () {
    it('returns file type for preview item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');
        $component->previewItemId = 'readme.txt';

        $fileType = $component->previewFileType;

        expect($fileType)->toBeInstanceOf(\MWGuerra\FileManager\Contracts\FileTypeContract::class);
        expect($fileType->identifier())->toBe('text');
    });

    it('returns null when no preview item', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        expect($component->previewFileType)->toBeNull();
    });
});

describe('inheritance', function () {
    it('extends EmbeddedFileManager', function () {
        expect(is_subclass_of(EmbeddedFileSystem::class, \MWGuerra\FileManager\Livewire\EmbeddedFileManager::class))->toBeTrue();
    });

    it('is a Livewire component', function () {
        expect(is_subclass_of(EmbeddedFileSystem::class, \Livewire\Component::class))->toBeTrue();
    });
});

describe('refresh', function () {
    it('can refresh component', function () {
        $component = new EmbeddedFileSystem();
        $component->mount(disk: 'testing');

        // Should not throw
        $component->refresh();

        expect(true)->toBeTrue();
    });
});

describe('stylesheet', function () {
    it('is loaded only on the pages that render the file manager', function () {
        $stylesheet = FilamentAsset::getStyles(['mwguerra/filemanager'])[0];

        // Filament prints a registered stylesheet on every page of every panel unless it is loaded on request.
        expect($stylesheet->isLoadedOnRequest())->toBeTrue();

        Route::get('/without-file-manager', fn () => Blade::render('<html><head></head><body></body></html>'));
        Route::get('/with-file-manager', fn () => Blade::render('<html><head></head><body><livewire:embedded-file-system /></body></html>'));

        $this->get('/without-file-manager')->assertDontSee($stylesheet->getHref(), false);
        $this->get('/with-file-manager')->assertSee($stylesheet->getHref(), false);
    });
});
