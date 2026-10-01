{{--
    The media library.

    The page is wrapped in `x-filament::page`, which is what puts the header
    actions and the action modals on it. Without it the page renders bare: the
    actions have nowhere to open, so a button that mounts one does nothing.

    The layout is built from Filament's own components — `section` for the two
    panes, `callout` for the drag hint, `badge` for the tile meta,
    `input.wrapper` for the search field, the action objects themselves for the
    per-item buttons, `empty-state` and `pagination` — so the page follows the
    panel's theme, its dark mode and its collapse behaviour without restating
    any of it. Rendering the actions rather than hand-wiring a click to
    `mountAction()` is what gives the buttons their loading state and, for the
    destructive ones, a confirmation modal.

    The rules below are the ones Filament has no view component for: the two
    panes side by side, the folder rows, the media grid, the drag target state,
    and the breakpoint where the sidebar stops fitting next to the grid. They
    are structural, not decorative. A package view is not part of the panel's
    compiled theme, so the utility classes Filament uses in its own Blade files
    are not available here and cannot be relied on.

    The view owns the drag and drop: `x-data` on the root holds the media item
    being dragged, every folder row accepts a drop, and the drop calls the page's
    `moveMedia`.
--}}
@php
    use Illuminate\Support\Number;
@endphp

<x-filament::page>
    <div class="bkm" x-data="{ dragged: null, over: null, justDragged: false }">
            <style>
            .bkm {
                --bkm-radius: 0.5rem;
                --bkm-border: var(--gray-200, rgb(229 231 235));
                --bkm-surface: var(--gray-50, rgb(249 250 251));
                --bkm-muted: var(--gray-500, rgb(107 114 128));
                --bkm-accent: var(--primary-600, rgb(4 120 87));
            }

            .dark .bkm {
                --bkm-border: var(--gray-700, rgb(55 65 81));
                --bkm-surface: var(--gray-900, rgb(24 24 27));
                --bkm-muted: var(--gray-400, rgb(156 163 175));
            }

            /* Only the folder rows need this. The search field and the buttons carry
               a Filament focus ring of their own, and a second ring drawn on top of
               one is what makes a focused field look like it has a double border. */
            .bkm button:focus-visible {
                outline: 2px solid var(--bkm-accent);
                outline-offset: 2px;
            }

            .bkm :is(button, [role='button']) {
                touch-action: manipulation;
            }

            /* Two panes side by side: the folders, then the files. */
            .bkm-body {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
            }

            .bkm-folders {
                flex: 0 0 15rem;
                min-width: 0;
            }

            .bkm-files {
                flex: 1 1 auto;
                min-width: 0;
            }

            /* A folder row is a navigation row, which Filament has no view
               component for. `aria-current` marks the open folder, so the one rule
               that styles it does not need to know which folder that is. */
            .bkm-folder {
                display: flex;
                align-items: center;
                gap: 0.25rem;
                min-width: 0;
                padding-right: 0.25rem;
                border-radius: var(--bkm-radius);
                user-select: none;
            }

            .bkm-folder-label {
                display: flex;
                flex: 1 1 auto;
                align-items: center;
                gap: 0.5rem;
                min-width: 0;
                padding: 0.375rem 0.5rem;
                border: 0;
                border-radius: var(--bkm-radius);
                background: none;
                color: inherit;
                font: inherit;
                text-align: start;
                cursor: pointer;
            }

            .bkm-folder-label > svg {
                flex: 0 0 auto;
            }

            .bkm-folder-label > span {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .bkm-folder-label:hover,
            .bkm-folder-label[aria-current='page'] {
                background-color: var(--bkm-surface);
            }

            .bkm-folder-label[aria-current='page'] {
                font-weight: 600;
            }

            .bkm-folder.is-over .bkm-folder-label {
                outline: 2px dashed var(--bkm-accent);
                outline-offset: -2px;
            }

            .bkm-folder-count {
                flex: 0 0 auto;
                color: var(--bkm-muted);
                font-size: 0.75rem;
                font-variant-numeric: tabular-nums;
            }

            /* Both action groups keep their place in the layout whether or not they
               are showing, so a row never moves under the pointer reaching for it.

               They are dimmed rather than hidden: a control that only exists while
               the pointer is over its tile is a control nobody can find, and the
               grid is the one place in the panel where the editor is looking for
               the things they can do to a file. */
            .bkm-folder-actions,
            .bkm-folder-tile-actions,
            .bkm-tile-actions {
                display: flex;
                flex: 0 0 auto;
                align-items: center;
                gap: 0.125rem;
                opacity: 0.55;
                transition: opacity 75ms;
            }

            .bkm-breadcrumbs {
                margin-bottom: 0.75rem;
            }

            .bkm-search {
                width: 100%;
                max-width: 24rem;
                margin-bottom: 0.75rem;
            }

            .bkm-hint {
                margin-bottom: 0.75rem;
            }

            .bkm-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
                gap: 1rem;
            }

            .bkm-tile {
                position: relative;
                display: flex;
                flex-direction: column;
                border: 1px solid var(--bkm-border);
                border-radius: var(--bkm-radius);
                overflow: hidden;
                user-select: none;
            }

            /* A folder is drawn on the same cell as a file, so that the grid
               reads the way a file browser does: what is here, then what is in
               it. The cell is the same square the image of a file fills. */
            .bkm-folder-tile {
                position: relative;
                display: flex;
                border: 1px solid var(--bkm-border);
                border-radius: var(--bkm-radius);
                background-color: var(--bkm-surface);
                overflow: hidden;
                user-select: none;
            }

            /* The label fills the cell so that the whole tile is the target, and
               the cell is the same square the image of a file fills. */
            .bkm-folder-tile-label {
                display: flex;
                flex: 1 1 auto;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                min-width: 0;
                aspect-ratio: 1 / 1;
                padding: 0.75rem;
                border: 0;
                background: none;
                color: inherit;
                font: inherit;
                cursor: pointer;
            }

            .bkm-folder-tile-icon {
                width: 2.5rem;
                height: 2.5rem;
            }

            .bkm-folder-tile-name {
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 0.8125rem;
                font-weight: 500;
            }

            .bkm-folder-tile.is-over {
                outline: 2px dashed var(--bkm-accent);
                outline-offset: -2px;
            }

            /* The image opens the edit form, so it is a button. It is drawn without
               a frame of its own: the tile around it is already the frame. */
            .bkm-tile-preview {
                display: block;
                width: 100%;
                padding: 0;
                border: 0;
                background: none;
                cursor: pointer;
            }

            .bkm-tile-image {
                display: block;
                width: 100%;
                aspect-ratio: 1 / 1;
                object-fit: cover;
            }

            .bkm-tile-caption {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.25rem;
                margin-top: auto;
                padding: 0.5rem;
            }

            /* The name grows to fill the first row, so the size sits beside it. The
               folder is a plain line of its own rather than a badge, because a badge
               wraps its own text and would make the tile three lines tall. */
            .bkm-tile-name {
                flex: 1 1 0;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 0.8125rem;
            }

            .bkm-tile-folder {
                display: flex;
                flex: 1 1 100%;
                align-items: center;
                gap: 0.25rem;
                min-width: 0;
                color: var(--bkm-muted);
                font-size: 0.6875rem;
            }

            .bkm-tile-folder > span {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* The two overlay groups sit on top of a photograph, where an icon with
               no backdrop is unreadable on a light one. The surface behind them
               comes from the same variable as the tile, so it follows the theme. */
            .bkm-folder-tile-actions,
            .bkm-tile-actions {
                position: absolute;
                top: 0.25rem;
                right: 0.25rem;
                padding: 0.125rem;
                border-radius: var(--bkm-radius);
                background-color: var(--bkm-surface);
                box-shadow: 0 1px 2px rgb(0 0 0 / 0.15);
            }

            .bkm-pagination {
                margin-top: 1rem;
            }

            /* A pointer that cannot hover never gets the pointer-over that brings
               these controls to full strength, so on touch they are simply shown. */
            @media (hover: hover) and (pointer: fine) {
                .bkm-folder,
                .bkm-folder-tile,
                .bkm-tile {
                    cursor: grab;
                }

                .bkm-folder-tile-label {
                    cursor: pointer;
                }

                .bkm-folder:hover .bkm-folder-actions,
                .bkm-folder:focus-within .bkm-folder-actions,
                .bkm-folder-tile:hover .bkm-folder-tile-actions,
                .bkm-folder-tile:focus-within .bkm-folder-tile-actions,
                .bkm-tile:hover .bkm-tile-actions,
                .bkm-tile:focus-within .bkm-tile-actions {
                    opacity: 1;
                }
            }

            @media (hover: none), (pointer: coarse) {
                .bkm-folder-actions,
                .bkm-folder-tile-actions,
                .bkm-tile-actions {
                    opacity: 1;
                }

                .bkm-hint {
                    display: none;
                }
            }

            /* A sidebar and a grid of images cannot share a tablet in portrait, so
               below this width the folders move above the files and the folders
               section folds itself out of the way. */
            @media (max-width: 63.99rem) {
                .bkm-body {
                    flex-direction: column;
                    align-items: stretch;
                }

                .bkm-folders {
                    flex: 0 0 auto;
                    width: 100%;
                }
            }

            /* On a phone the images get the width the screen can spare. */
            @media (max-width: 47.99rem) {
                .bkm-grid {
                    grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr));
                    gap: 0.75rem;
                }
            }
        </style>

        <div class="bkm-body">
            <x-filament::section
                class="bkm-folders"
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
                    x-bind:class="{ 'is-over': over === 'all' }"
                    x-on:dragenter.prevent="if (dragged !== null) { over = 'all' }"
                    x-on:dragover.prevent
                    x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                    x-on:drop.prevent="$wire.moveMedia(dragged, null); dragged = null; over = null"
                >
                    <button
                        type="button"
                        class="bkm-folder-label"
                        @if ($currentFolder === null) aria-current="page" @endif
                        wire:click="selectFolder(null)"
                    >
                        <x-filament::icon icon="heroicon-o-squares-2x2" />

                        <span>{{ __('basekit-laravel-media::basekit-laravel-media.all_files') }}</span>
                    </button>

                    <span class="bkm-folder-count">{{ $totalCount }}</span>
                </div>

                @foreach ($folders as ['folder' => $folder, 'depth' => $depth])
                    <div
                        wire:key="folder-{{ $folder->getKey() }}"
                        data-folder="{{ $folder->slug }}"
                        data-depth="{{ $depth }}"
                        class="bkm-folder"
                        style="padding-left: {{ $depth * 0.75 }}rem"
                        x-bind:class="{ 'is-over': over === {{ $folder->getKey() }} }"
                        x-on:dragenter.prevent="if (dragged !== null) { over = {{ $folder->getKey() }} }"
                        x-on:dragover.prevent
                        x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                        x-on:drop.prevent="$wire.moveMedia(dragged, {{ $folder->getKey() }}); dragged = null; over = null"
                    >
                        <button
                            type="button"
                            class="bkm-folder-label"
                            @if ($currentFolder?->is($folder)) aria-current="page" @endif
                            wire:click="selectFolder({{ $folder->getKey() }})"
                        >
                            <x-filament::icon icon="heroicon-o-folder" />

                            <span>{{ $folder->name }}</span>
                        </button>

                        <span class="bkm-folder-count">{{ $folder->media_count }}</span>

                        <span class="bkm-folder-actions">
                            {{ ($this->deleteFolderAction)(['id' => $folder->getKey()])->iconButton()->size('xs') }}
                        </span>
                    </div>
                @endforeach
            </x-filament::section>

            <x-filament::section
                class="bkm-files"
                :heading="$folderLabel"
                :icon="$currentFolder === null ? 'heroicon-o-photo' : 'heroicon-o-folder-open'"
                compact
                collapsible
            >
            <x-filament::breadcrumbs
                class="bkm-breadcrumbs"
                :breadcrumbs="$breadcrumbs"
            />

            <x-filament::input.wrapper
                class="bkm-search"
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

                <x-filament::callout
                    class="bkm-hint"
                    color="gray"
                    icon="heroicon-o-arrow-right-start-on-rectangle"
                    :description="__('basekit-laravel-media::basekit-laravel-media.drag_hint')"
                />

                @if ($childFolders->isEmpty() && $media->isEmpty())
                    <x-filament::empty-state
                        :icon="$currentFolder === null ? 'heroicon-o-photo' : 'heroicon-o-folder-open'"
                        :heading="$currentFolder === null
                            ? __('basekit-laravel-media::basekit-laravel-media.empty')
                            : __('basekit-laravel-media::basekit-laravel-media.empty_folder', ['folder' => $currentFolder->name])"
                        :description="__('basekit-laravel-media::basekit-laravel-media.empty_help')"
                    />
                @else
                    <div class="bkm-grid">
                        @foreach ($childFolders as $folder)
                            <div
                                wire:key="folder-tile-{{ $folder->getKey() }}"
                                data-folder-tile="{{ $folder->slug }}"
                                class="bkm-folder-tile"
                                x-bind:class="{ 'is-over': over === {{ $folder->getKey() }} }"
                                x-on:dragenter.prevent="if (dragged !== null) { over = {{ $folder->getKey() }} }"
                                x-on:dragover.prevent
                                x-on:dragleave="if (! $event.currentTarget.contains($event.relatedTarget)) { over = null }"
                                x-on:drop.prevent="$wire.moveMedia(dragged, {{ $folder->getKey() }}); dragged = null; over = null"
                            >
                                <button
                                    type="button"
                                    class="bkm-folder-tile-label"
                                    wire:click="selectFolder({{ $folder->getKey() }})"
                                >
                                    <x-filament::icon
                                        class="bkm-folder-tile-icon"
                                        icon="heroicon-o-folder"
                                    />

                                    <span class="bkm-folder-tile-name" title="{{ $folder->name }}">{{ $folder->name }}</span>
                                </button>

                                <span class="bkm-folder-tile-actions">
                                    {{ ($this->deleteFolderAction)(['id' => $folder->getKey()])->iconButton()->size('xs') }}
                                </span>
                            </div>
                        @endforeach

                        @foreach ($media as $item)
                            <div
                                wire:key="media-{{ $item->id }}"
                                class="bkm-tile"
                                draggable="true"
                                x-on:pointerdown="justDragged = false"
                                x-on:dragstart="dragged = {{ $item->id }}; justDragged = true; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', {{ $item->id }})"
                                x-on:dragend="dragged = null; over = null"
                            >
                                <button
                                    type="button"
                                    class="bkm-tile-preview"
                                    x-on:click="if (! justDragged) $wire.mountAction('edit', { id: {{ $item->id }} })"
                                    aria-label="{{ __('basekit-laravel-media::basekit-laravel-media.preview') }}: {{ $item->name }}"
                                >
                                    <img
                                        class="bkm-tile-image"
                                        src="{{ $item->storageUrl() }}"
                                        alt="{{ $item->alt ?? $item->name }}"
                                        loading="lazy"
                                    />
                                </button>

                                <div class="bkm-tile-actions">
                                    {{ ($this->editAction)(['id' => $item->id])->iconButton()->size('xs') }}

                                    {{ ($this->moveAction)(['id' => $item->id])->iconButton()->size('xs') }}

                                    {{ ($this->deleteAction)(['id' => $item->id])->iconButton()->size('xs') }}
                                </div>

                                <div class="bkm-tile-caption">
                                    <span class="bkm-tile-name" title="{{ $item->name }}">{{ $item->name }}</span>

                                    <x-filament::badge color="gray" size="xs">
                                        {{ Number::fileSize($item->size) }}
                                    </x-filament::badge>

                                    @if ($currentFolder === null && $item->folder)
                                        <span class="bkm-tile-folder" title="{{ $item->folder->name }}">
                                            <x-filament::icon icon="heroicon-o-folder" />

                                            <span>{{ $item->folder->name }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($media->hasPages())
                        <div class="bkm-pagination">
                            <x-filament::pagination :paginator="$media" />
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament::page>
