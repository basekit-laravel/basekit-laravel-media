<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament\Pages;

use BackedEnum;
use BasekitLaravel\BasekitLaravelMedia\Filament\Concerns\ConfiguresMediaUpload;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaAccess;
use BasekitLaravel\BasekitLaravelMedia\Support\MediaWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

/**
 * The whole media admin on one screen: the folder tree on the left, the files of the
 * open folder in a grid on the right, and every way of changing something — upload,
 * create a folder, move, edit, delete — available from the same page.
 *
 * A file moves between folders by being dropped on a folder in the tree; the *Move*
 * action does the same through a form, so the drag is a shortcut rather than the only
 * way.
 */
class MediaLibrary extends Page
{
    use ConfiguresMediaUpload;
    use WithPagination;

    /**
     * The group key a top-level folder hangs off, chosen so it cannot collide with
     * a folder ID.
     */
    private const string ROOT = 'root';

    protected string $view = 'basekit-laravel-media::pages.media-library';

    protected static ?string $slug = 'media';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    protected int $perPage = 24;

    /**
     * The open folder, or `null` for every file in the library. It lives in the URL
     * so a folder can be linked to and the back button walks out of it again.
     */
    #[Url(as: 'folder')]
    public ?int $folder = null;

    #[Url(as: 'q')]
    public string $search = '';

    /**
     * The tree and the open folder are read by the view and by the forms of the
     * action modals of the same request, so each is resolved once per request.
     *
     * @var array<int, array{folder: MediaFolder, depth: int}>|null
     */
    private ?array $resolvedFolderTree = null;

    private bool $folderIsResolved = false;

    private ?MediaFolder $openFolder = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('basekit-laravel-media::basekit-laravel-media.navigation_group');
    }

    /**
     * Filament lets every authenticated panel user reach a custom page unless the
     * page says otherwise, which would put the whole library — delete buttons
     * included — in front of anyone who can sign in. `MediaAccess` decides instead.
     */
    public static function canAccess(): bool
    {
        return MediaAccess::allows('view');
    }

    public static function getNavigationLabel(): string
    {
        return __('basekit-laravel-media::basekit-laravel-media.media');
    }

    public function getTitle(): string
    {
        return __('basekit-laravel-media::basekit-laravel-media.media');
    }

    /**
     * Open a folder, or pass `null` to see every file. The page is reset because
     * the last page of the previous folder is not the last page of this one.
     */
    public function selectFolder(?int $folder): void
    {
        $this->folder = $folder;
        $this->resetPage();
    }

    /**
     * File a media item into a folder. Both the drop on the tree and the *Move*
     * action's form land here, so the two ways of moving a file behave the same.
     *
     * Only the metadata changes, so a page that already refers to the file keeps
     * working. A drop naming a file or folder that no longer exists is ignored.
     */
    public function moveMedia(?int $media, ?int $folder): void
    {
        if ($media === null) {
            return;
        }

        $item = Media::query()->find($media);

        if (! $item instanceof Media) {
            return;
        }

        // The drop calls straight into this method, so the check the hidden buttons
        // got is made again here. The item is passed so a policy the application
        // registered for `Media` gets to answer for this row.
        MediaAccess::authorize('update', $item);

        $item->folder_id = $folder === null ? null : MediaFolder::query()->find($folder)?->getKey();
        $item->save();

        Notification::make()
            ->title(__('basekit-laravel-media::basekit-laravel-media.moved'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        if (! MediaAccess::allows('create')) {
            return [];
        }

        return [
            $this->uploadAction(),
            $this->newFolderAction(),
        ];
    }

    /**
     * Uploads land in the folder that is open, so the file appears where the editor
     * is looking without a second choice to make.
     */
    public function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('basekit-laravel-media::basekit-laravel-media.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => MediaAccess::allows('create'))
            ->modalHeading(fn (): string => __('basekit-laravel-media::basekit-laravel-media.upload_to', [
                'folder' => $this->openFolderLabel(),
            ]))
            ->modalSubmitActionLabel(__('basekit-laravel-media::basekit-laravel-media.upload'))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(2)
                ->components([
                    $this->mediaUpload(directory: Media::uploadDirectory($this->currentFolder()))
                        ->columnSpanFull(),
                    TextInput::make('alt')
                        ->label(__('basekit-laravel-media::basekit-laravel-media.alt'))
                        ->maxLength(255),
                ]))
            ->action(function (array $data): void {
                MediaAccess::authorize('create');

                app(MediaWriter::class)->create([
                    ...$data,
                    'folder_id' => $this->folder,
                ]);

                Notification::make()
                    ->title(__('basekit-laravel-media::basekit-laravel-media.upload_success'))
                    ->success()
                    ->send();
            });
    }

    /**
     * A new folder is a child of the folder that is open, which is what makes the
     * tree grow where the editor is looking. Creating one also walks into it.
     */
    public function newFolderAction(): Action
    {
        return Action::make('newFolder')
            ->label(__('basekit-laravel-media::basekit-laravel-media.new_folder'))
            ->icon('heroicon-o-folder-plus')
            ->visible(fn (): bool => MediaAccess::allows('create'))
            ->modalHeading(fn (): string => __('basekit-laravel-media::basekit-laravel-media.new_folder_in', [
                'folder' => $this->openFolderLabel(),
            ]))
            ->modalSubmitActionLabel(__('basekit-laravel-media::basekit-laravel-media.create'))
            ->schema([
                TextInput::make('name')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                MediaAccess::authorize('create');

                $folder = MediaFolder::createWithUniqueSlug([
                    'name' => $data['name'],
                    'parent_id' => $this->folder,
                ]);

                $this->folder = $folder->getKey();
                $this->resetPage();

                Notification::make()
                    ->title(__('basekit-laravel-media::basekit-laravel-media.folder_created'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The metadata of a single file, reached from the buttons on its tile. The view
     * renders this once per tile and passes the item as an argument, so the buttons
     * carry their own arguments rather than the markup hand-wiring a modal.
     */
    public function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('basekit-laravel-media::basekit-laravel-media.edit'))
            ->icon('heroicon-o-pencil-square')
            ->tooltip(__('basekit-laravel-media::basekit-laravel-media.edit'))
            ->visible(fn (): bool => MediaAccess::allows('update'))
            ->modalHeading(fn (array $arguments): string => $this->mediaName($arguments))
            ->modalContent(fn (array $arguments): View => view('basekit-laravel-media::media.edit-preview', [
                'media' => $this->mediaRecord($arguments),
            ]))
            ->fillForm(fn (array $arguments): array => $this->mediaRecord($arguments)?->only(['name', 'alt']) ?? [])
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label(__('basekit-laravel-media::basekit-laravel-media.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('alt')
                        ->label(__('basekit-laravel-media::basekit-laravel-media.alt'))
                        ->maxLength(255),
                ]))
            ->action(function (array $arguments, array $data): void {
                $media = $this->mediaRecord($arguments);

                if (! $media instanceof Media) {
                    return;
                }

                MediaAccess::authorize('update', $media);

                $media->name = $data['name'];
                $media->alt = filled($data['alt'] ?? null) ? $data['alt'] : null;
                $media->save();

                Notification::make()
                    ->title(__('basekit-laravel-media::basekit-laravel-media.saved'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Moving a file without dragging it. The form is prefilled with the folder the
     * file is in and offers the whole tree, so a file can move more than one level
     * in a single step.
     */
    public function moveAction(): Action
    {
        return Action::make('move')
            ->label(__('basekit-laravel-media::basekit-laravel-media.move'))
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->tooltip(__('basekit-laravel-media::basekit-laravel-media.move'))
            ->visible(fn (): bool => MediaAccess::allows('update'))
            ->modalHeading(__('basekit-laravel-media::basekit-laravel-media.move_to'))
            ->modalSubmitActionLabel(__('basekit-laravel-media::basekit-laravel-media.move'))
            ->fillForm(fn (array $arguments): array => [
                'folder_id' => $this->mediaRecord($arguments)?->folder_id,
            ])
            ->schema([
                Select::make('folder_id')
                    ->label(__('basekit-laravel-media::basekit-laravel-media.folder'))
                    ->options(fn (): array => $this->folderOptions())
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->moveMedia(
                    filled($arguments['id'] ?? null) ? (int) $arguments['id'] : null,
                    filled($data['folder_id'] ?? null) ? (int) $data['folder_id'] : null,
                );
            });
    }

    /**
     * Deleting a file is confirmed in a modal that names it and says whether the
     * deletion can be undone, rather than through the browser's own `confirm()`.
     */
    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('basekit-laravel-media::basekit-laravel-media.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->tooltip(__('basekit-laravel-media::basekit-laravel-media.delete'))
            ->requiresConfirmation()
            ->visible(fn (): bool => MediaAccess::allows('delete'))
            ->modalHeading(fn (array $arguments): string => __('basekit-laravel-media::basekit-laravel-media.delete_file', [
                'file' => $this->mediaName($arguments),
            ]))
            ->modalDescription(fn (): string => Media::softDeletesEnabled()
                ? __('basekit-laravel-media::basekit-laravel-media.delete_file_help')
                : __('basekit-laravel-media::basekit-laravel-media.delete_file_help_permanently'))
            ->modalSubmitActionLabel(__('basekit-laravel-media::basekit-laravel-media.delete'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel(__('basekit-laravel-media::basekit-laravel-media.cancel'))
            ->action(function (array $arguments): void {
                $media = $this->mediaRecord($arguments);

                if (! $media instanceof Media) {
                    return;
                }

                MediaAccess::authorize('delete', $media);

                $media->delete();
            });
    }

    /**
     * Deleting a folder removes it and its subfolders, but not its files: the
     * foreign key nulls rather than cascades, so they fall back to *All files*.
     */
    public function deleteFolderAction(): Action
    {
        return Action::make('deleteFolder')
            ->label(__('basekit-laravel-media::basekit-laravel-media.delete_folder'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->tooltip(__('basekit-laravel-media::basekit-laravel-media.delete_folder'))
            ->requiresConfirmation()
            ->visible(fn (): bool => MediaAccess::allows('delete'))
            ->modalHeading(fn (array $arguments): string => __('basekit-laravel-media::basekit-laravel-media.delete_folder_named', [
                'folder' => $this->folderName($arguments),
            ]))
            ->modalDescription(__('basekit-laravel-media::basekit-laravel-media.delete_folder_help'))
            ->modalSubmitActionLabel(__('basekit-laravel-media::basekit-laravel-media.delete'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel(__('basekit-laravel-media::basekit-laravel-media.cancel'))
            ->action(function (array $arguments): void {
                $folder = $this->folderRecord($arguments);

                if (! $folder instanceof MediaFolder) {
                    return;
                }

                MediaAccess::authorize('delete', $folder);

                $folder->delete();

                if ($this->folder === $folder->getKey()) {
                    $this->folder = null;
                }
            });
    }

    /**
     * What the signed-in user may do here, for a view that draws one button per
     * ability rather than a single set of buttons that is right or wrong for
     * everyone.
     *
     * @return array<string, bool>
     */
    protected function abilities(): array
    {
        return MediaAccess::all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'folders' => $this->folderTree(),
            'childFolders' => $this->childFolders(),
            'breadcrumbs' => $this->folderBreadcrumbs(),
            'folderLabel' => $this->openFolderLabel(),
            'media' => $this->media(),
            'totalCount' => Media::query()->count(),
            'currentFolder' => $this->currentFolder(),
            'abilities' => $this->abilities(),
        ];
    }

    /**
     * The folders inside the open folder, in the order they are drawn above its
     * files. They are drawn whole while the files are paginated: a folder list long
     * enough to matter is one the sidebar tree already serves.
     *
     * @return Collection<int, MediaFolder>
     */
    protected function childFolders(): Collection
    {
        return MediaFolder::query()
            ->withCount('media')
            ->when(
                $this->folder === null,
                fn (Builder $query): Builder => $query->whereNull('parent_id'),
                fn (Builder $query): Builder => $query->where('parent_id', $this->folder),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * The trail from the top of the library down to the open folder. A chain that
     * never reaches the top — or that reaches the folder itself — stops where it
     * breaks rather than running for ever.
     *
     * @return array<int, MediaFolder>
     */
    protected function folderTrail(): array
    {
        $trail = [];
        $walked = [];
        $folder = $this->currentFolder();

        while ($folder instanceof MediaFolder) {
            if (isset($walked[$folder->getKey()])) {
                break;
            }

            $walked[$folder->getKey()] = true;
            array_unshift($trail, $folder);

            $folder = $folder->parent_id === null
                ? null
                : MediaFolder::query()->find($folder->parent_id);
        }

        return $trail;
    }

    /**
     * The breadcrumb for the open folder as the breadcrumb component takes it: each
     * segment's target is its key and its label is the folder name, except the last
     * segment, which has no key and so renders as the page the editor is on.
     *
     * Segments link rather than call the page, because the open folder is in the URL:
     * that way the trail can be shared and the back button walks out of a folder.
     *
     * @return array<int|string, string>
     */
    protected function folderBreadcrumbs(): array
    {
        $trail = $this->folderTrail();

        // The whole library is the one place the editor is when no folder is
        // open, so it is then the page the editor is on rather than a link to it.
        if ($trail === []) {
            return [0 => __('basekit-laravel-media::basekit-laravel-media.all_files')];
        }

        $breadcrumbs = [static::getUrl() => __('basekit-laravel-media::basekit-laravel-media.all_files')];
        $last = array_key_last($trail);

        foreach ($trail as $depth => $folder) {
            $breadcrumbs[$depth === $last
                ? $depth
                : static::getUrl(['folder' => $folder->getKey()])] = $folder->name;
        }

        return $breadcrumbs;
    }

    /**
     * Every folder, flattened into drawing order and paired with its depth, so the
     * view walks the tree with one loop and no recursion.
     *
     * @return array<int, array{folder: MediaFolder, depth: int}>
     */
    protected function folderTree(): array
    {
        if ($this->resolvedFolderTree !== null) {
            return $this->resolvedFolderTree;
        }

        $folders = MediaFolder::query()
            ->withCount('media')
            ->orderBy('name')
            ->get();

        $byParent = $folders->groupBy(fn (MediaFolder $folder): string => (string) ($folder->parent_id ?? self::ROOT));

        $tree = [];
        $visited = [];

        $append = function (?int $parentId, int $depth) use (&$append, &$tree, &$visited, $byParent): void {
            foreach ($byParent->get((string) ($parentId ?? self::ROOT)) ?? [] as $folder) {
                // A folder that claims an ancestor as its parent would otherwise
                // be drawn for ever, so a folder is only ever drawn once.
                if (isset($visited[$folder->getKey()])) {
                    continue;
                }

                $visited[$folder->getKey()] = true;
                $tree[] = ['folder' => $folder, 'depth' => $depth];

                $append($folder->getKey(), $depth + 1);
            }
        };

        $append(null, 0);

        // A folder the walk below did not reach would be invisible in the tree and
        // unreachable from the page, and its files would be too. That needs a parent
        // chain written straight to the table, but an unreachable folder is still
        // worth drawing, so the leftovers go on top.
        foreach ($folders as $folder) {
            if (isset($visited[$folder->getKey()])) {
                continue;
            }

            $visited[$folder->getKey()] = true;
            $tree[] = ['folder' => $folder, 'depth' => 0];

            $append($folder->getKey(), 1);
        }

        return $this->resolvedFolderTree = $tree;
    }

    /**
     * The tree as `id => name`, indented by depth so a folder reads as nested in the
     * dropdown of the *Move* action.
     *
     * @return array<int|string, string>
     */
    protected function folderOptions(): array
    {
        $options = [];

        foreach ($this->folderTree() as ['folder' => $folder, 'depth' => $depth]) {
            $options[$folder->getKey()] = str_repeat('— ', $depth).$folder->name;
        }

        return $options;
    }

    /**
     * The files of the open folder, or every file in the library when no folder
     * is open.
     *
     * @return LengthAwarePaginator<int, Media>
     */
    protected function media(): LengthAwarePaginator
    {
        return Media::query()
            ->with('folder')
            ->when(
                $this->folder !== null,
                fn (Builder $query): Builder => $query->where('folder_id', $this->folder),
            )
            ->when(
                filled($this->search),
                fn (Builder $query): Builder => $query->where('name', 'like', '%'.$this->search.'%'),
            )
            ->latest('id')
            ->paginate($this->perPage);
    }

    /**
     * The folder the page is looking into, or `null` for the whole library. A folder
     * deleted since the page was rendered reads as the whole library, not as an error.
     */
    protected function currentFolder(): ?MediaFolder
    {
        if (! $this->folderIsResolved) {
            $this->folderIsResolved = true;
            $this->openFolder = $this->folder === null
                ? null
                : MediaFolder::query()->find($this->folder);
        }

        return $this->openFolder;
    }

    /**
     * The name the open folder is called by in the page's own wording.
     */
    protected function openFolderLabel(): string
    {
        $folder = $this->currentFolder();

        if ($folder === null) {
            return __('basekit-laravel-media::basekit-laravel-media.all_files');
        }

        return $folder->name;
    }

    /**
     * The media item an action was mounted for, or `null` when its arguments name an
     * item deleted since the page was rendered.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function mediaRecord(array $arguments): ?Media
    {
        $id = $arguments['id'] ?? null;

        return is_numeric($id) ? Media::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function folderRecord(array $arguments): ?MediaFolder
    {
        $id = $arguments['id'] ?? null;

        return is_numeric($id) ? MediaFolder::query()->find((int) $id) : null;
    }

    /**
     * The name a media item is called by, falling back to a neutral label when the
     * file is gone.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function mediaName(array $arguments): string
    {
        $media = $this->mediaRecord($arguments);

        if ($media === null) {
            return __('basekit-laravel-media::basekit-laravel-media.media_item');
        }

        return $media->name;
    }

    /**
     * The name a folder is called by, falling back to a neutral label when the
     * folder is gone.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function folderName(array $arguments): string
    {
        $folder = $this->folderRecord($arguments);

        if ($folder === null) {
            return __('basekit-laravel-media::basekit-laravel-media.media_item');
        }

        return $folder->name;
    }
}
