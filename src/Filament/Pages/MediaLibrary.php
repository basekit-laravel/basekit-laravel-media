<?php

declare(strict_types=1);

namespace BasekitLaravel\BasekitLaravelMedia\Filament\Pages;

use BackedEnum;
use BasekitLaravel\BasekitLaravelMedia\Filament\Concerns\ConfiguresMediaUpload;
use BasekitLaravel\BasekitLaravelMedia\Models\Media;
use BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder;
use BasekitLaravel\BasekitLaravelMedia\Support\FolderTree;
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
 * The whole media admin on one screen: the folder tree on the left, the files of
 * the open folder on the right, and every way of changing something — upload,
 * create a folder, move, edit, delete — from the same page.
 */
class MediaLibrary extends Page
{
    use ConfiguresMediaUpload;
    use WithPagination;

    protected string $view = 'basekit-laravel-media::pages.media-library';

    protected static ?string $slug = 'media';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    protected int $perPage = 24;

    /**
     * The open folder, or `null` for every file in the library. It lives in the
     * URL so a folder can be linked to.
     */
    #[Url(as: 'folder')]
    public ?int $folder = null;

    #[Url(as: 'q')]
    public string $search = '';

    /**
     * Resolved once per request; the view and the action modals all read it.
     */
    private ?FolderTree $tree = null;

    private bool $folderIsResolved = false;

    private ?MediaFolder $openFolder = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('basekit-laravel-media::basekit-laravel-media.navigation_group');
    }

    /**
     * Filament reaches every authenticated user by default, so this asks
     * `MediaAccess` instead.
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
     * Open a folder, or pass `null` to see every file. Pagination is reset,
     * because the last page of one folder is not the last page of another.
     */
    public function selectFolder(?int $folder): void
    {
        $this->folder = $folder;
        $this->resetPage();
    }

    /**
     * File a media item into a folder. Both the drop on the tree and the *Move*
     * form land here; only the metadata changes.
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

        // The drop reaches this method directly, so it asks here as well.
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
     * Uploads land in the folder that is open.
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
     * A new folder is a child of the folder that is open; creating one also
     * walks into it.
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
     * The metadata of a single file, reached from the buttons on its tile. Each
     * button carries the item id as its argument.
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
     * Moving a file without dragging it. The form is prefilled with the folder
     * the file is in.
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
     * Deleting a file, confirmed in a modal that names it and says whether the
     * deletion can be undone.
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
     * Deleting a folder removes it and its subfolders, but not its files: they
     * fall back to *All files*.
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
     * ability.
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
            'folders' => $this->folderTree()->branches(),
            'childFolders' => $this->childFolders(),
            'breadcrumbs' => $this->folderBreadcrumbs(),
            'folderLabel' => $this->openFolderLabel(),
            'emptyHeading' => $this->emptyHeading(),
            'media' => $this->media(),
            'totalCount' => Media::query()->count(),
            'currentFolder' => $this->currentFolder(),
            'abilities' => $this->abilities(),
        ];
    }

    /**
     * The folders inside the open folder, drawn whole while the files are
     * paginated.
     *
     * @return Collection<int, MediaFolder>
     */
    protected function childFolders(): Collection
    {
        return $this->folderTree()->children($this->folder);
    }

    /**
     * The breadcrumb for the open folder. The last segment has no key and
     * renders as the page itself.
     *
     * @return array<int|string, string>
     */
    protected function folderBreadcrumbs(): array
    {
        $trail = $this->folderTree()->trail($this->currentFolder());

        // With no folder open the whole library is the page itself, not a link
        // to it.
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
     * The folder hierarchy, resolved once per request.
     */
    protected function folderTree(): FolderTree
    {
        return $this->tree ??= new FolderTree;
    }

    /**
     * The tree as `id => name`, indented by depth so a folder reads as nested in
     * the dropdown of the *Move* action.
     *
     * @return array<int|string, string>
     */
    protected function folderOptions(): array
    {
        return $this->folderTree()->options();
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
     * The heading of the empty state, which differs for the whole library and
     * for an empty folder.
     */
    protected function emptyHeading(): string
    {
        $folder = $this->currentFolder();

        if ($folder === null) {
            return __('basekit-laravel-media::basekit-laravel-media.empty');
        }

        return __('basekit-laravel-media::basekit-laravel-media.empty_folder', ['folder' => $folder->name]);
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
            return __('basekit-laravel-media::basekit-laravel-media.folder');
        }

        return $folder->name;
    }
}
