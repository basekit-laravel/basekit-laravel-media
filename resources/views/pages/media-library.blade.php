{{--
    The media library: the folder tree on the left, the files of the open folder
    on the right.

    Wrapped in `x-filament::page`, which puts the header actions and the action
    modals on it. The rules here are structural only: a package view is not part
    of the panel's compiled theme, so it cannot use Filament's utility classes.

    The view owns the drag and drop; a drop on a folder row calls `moveMedia`.
--}}

<x-filament::page>
    <div class="bkm-library" x-data="{ dragged: null, over: null, justDragged: false }">

        <div class="bkm-library__body">
            <x-filament::section
                class="bkm-library__folders"
                :heading="__('basekit-laravel-media::basekit-laravel-media.folders')"
                icon="heroicon-o-folder-open"
                compact
                collapsible
                x-init="if (window.matchMedia('(max-width: 63.99rem)').matches) isCollapsed = true"
            >
                <div
                    wire:key="folder-all"
                    data-folder="all"
                    data-depth="0"
                    class="bkm-folder"
                    x-bind:class="{ 'bkm-folder--over': over === 'all' }"
                    x-on:dragenter.prevent="if (dragged !== null) { over = 'all' }"
                    x-on:dragover.prevent
                    x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                    x-on:drop.prevent="$wire.moveMedia(dragged, null); dragged = null; over = null"
                >
                    <button
                        type="button"
                        class="bkm-folder__label"
                        @if ($currentFolder === null) aria-current="page" @endif
                        wire:click="selectFolder(null)"
                    >
                        <x-filament::icon icon="heroicon-o-squares-2x2" />

                        <span>{{ __('basekit-laravel-media::basekit-laravel-media.all_files') }}</span>
                    </button>

                    <span class="bkm-folder__count">{{ $totalCount }}</span>
                </div>

                @foreach ($folders as ['folder' => $folder, 'depth' => $depth])
                    <div
                        wire:key="folder-{{ $folder->getKey() }}"
                        data-folder="{{ $folder->slug }}"
                        data-depth="{{ $depth }}"
                        class="bkm-folder"
                        style="padding-left: {{ $depth * 0.75 }}rem"
                        x-bind:class="{ 'bkm-folder--over': over === {{ $folder->getKey() }} }"
                        x-on:dragenter.prevent="if (dragged !== null) { over = {{ $folder->getKey() }} }"
                        x-on:dragover.prevent
                        x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                        x-on:drop.prevent="$wire.moveMedia(dragged, {{ $folder->getKey() }}); dragged = null; over = null"
                    >
                        <button
                            type="button"
                            class="bkm-folder__label"
                            @if ($currentFolder?->is($folder)) aria-current="page" @endif
                            wire:click="selectFolder({{ $folder->getKey() }})"
                        >
                            <x-filament::icon icon="heroicon-o-folder" />

                            <span>{{ $folder->name }}</span>
                        </button>

                        <span class="bkm-folder__count">{{ $folder->media_count }}</span>

                        <span class="bkm-folder__actions">
                            @if ($abilities['delete'])
                                {{ ($this->deleteFolderAction)(['id' => $folder->getKey()])->iconButton()->size('xs') }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </x-filament::section>

            <x-filament::section
                class="bkm-library__files"
                :heading="$folderLabel"
                :icon="$currentFolder === null ? 'heroicon-o-photo' : 'heroicon-o-folder-open'"
                compact
                collapsible
            >
                <x-filament::breadcrumbs
                    class="bkm-library__breadcrumbs"
                    :breadcrumbs="$breadcrumbs"
                />

                <x-filament::input.wrapper
                    class="bkm-library__search"
                    prefix-icon="heroicon-m-magnifying-glass"
                >
                    <input
                        class="fi-input"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="{{ __('basekit-laravel-media::basekit-laravel-media.search') }}"
                        aria-label="{{ __('basekit-laravel-media::basekit-laravel-media.search') }}"
                    />
                </x-filament::input.wrapper>

                @if ($abilities['update'])
                    <x-filament::callout
                        class="bkm-library__hint"
                        color="gray"
                        icon="heroicon-o-arrow-right-start-on-rectangle"
                        :description="__('basekit-laravel-media::basekit-laravel-media.drag_hint')"
                    />
                @endif

                @if ($childFolders->isEmpty() && $media->isEmpty())
                    <x-filament::empty-state
                        :icon="$currentFolder === null ? 'heroicon-o-photo' : 'heroicon-o-folder-open'"
                        :heading="$emptyHeading"
                        :description="__('basekit-laravel-media::basekit-laravel-media.empty_help')"
                    />
                @else
                    <div class="bkm-library__grid">
                        @foreach ($childFolders as $folder)
                            <div
                                wire:key="folder-tile-{{ $folder->getKey() }}"
                                data-folder-tile="{{ $folder->slug }}"
                                class="bkm-folder-tile"
                                x-bind:class="{ 'bkm-folder-tile--over': over === {{ $folder->getKey() }} }"
                                x-on:dragenter.prevent="if (dragged !== null) { over = {{ $folder->getKey() }} }"
                                x-on:dragover.prevent
                                x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                                x-on:drop.prevent="$wire.moveMedia(dragged, {{ $folder->getKey() }}); dragged = null; over = null"
                            >
                                <button
                                    type="button"
                                    class="bkm-folder-tile__label"
                                    wire:click="selectFolder({{ $folder->getKey() }})"
                                >
                                    <x-filament::icon
                                        class="bkm-folder-tile__icon"
                                        icon="heroicon-o-folder"
                                    />

                                    <span class="bkm-folder-tile__name" title="{{ $folder->name }}">{{ $folder->name }}</span>
                                </button>

                                <span class="bkm-folder-tile__actions">
                                    @if ($abilities['delete'])
                                        {{ ($this->deleteFolderAction)(['id' => $folder->getKey()])->iconButton()->size('xs') }}
                                    @endif
                                </span>
                            </div>
                        @endforeach

                        @foreach ($media as $item)
                            <div
                                wire:key="media-{{ $item->id }}"
                                class="bkm-tile"
                                draggable="{{ $abilities['update'] ? 'true' : 'false' }}"
                                x-on:pointerdown="justDragged = false"
                                x-on:dragstart="dragged = {{ $item->id }}; justDragged = true; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', {{ $item->id }})"
                                x-on:dragend="dragged = null; over = null"
                            >
                                @if ($abilities['update'])
                                    <button
                                        type="button"
                                        class="bkm-tile__preview"
                                        x-on:click="if (! justDragged) $wire.mountAction('edit', { id: {{ $item->id }} })"
                                        aria-label="{{ __('basekit-laravel-media::basekit-laravel-media.preview') }}: {{ $item->name }}"
                                    >
                                        @include('basekit-laravel-media::media.tile-image', ['item' => $item])
                                    </button>
                                @else
                                    <div class="bkm-tile__preview">
                                        @include('basekit-laravel-media::media.tile-image', ['item' => $item])
                                    </div>
                                @endif

                                <div class="bkm-tile__actions">
                                    @if ($abilities['update'])
                                        {{ ($this->editAction)(['id' => $item->id])->iconButton()->size('xs') }}

                                        {{ ($this->moveAction)(['id' => $item->id])->iconButton()->size('xs') }}
                                    @endif

                                    @if ($abilities['delete'])
                                        {{ ($this->deleteAction)(['id' => $item->id])->iconButton()->size('xs') }}
                                    @endif
                                </div>

                                <div class="bkm-tile__caption">
                                    <span class="bkm-tile__name" title="{{ $item->name }}">{{ $item->name }}</span>

                                    <x-filament::badge color="gray" size="xs">
                                        {{ $item->humanSize() }}
                                    </x-filament::badge>

                                    @if ($currentFolder === null && $item->folder)
                                        <span class="bkm-tile__folder" title="{{ $item->folder->name }}">
                                            <x-filament::icon icon="heroicon-o-folder" />

                                            <span>{{ $item->folder->name }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($media->hasPages())
                        <div class="bkm-library__pagination">
                            <x-filament::pagination :paginator="$media" />
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament::page>
