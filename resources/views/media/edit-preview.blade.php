{{--
    The file being edited, shown above the edit form.

    Editing a file means deciding what to call it and what to tell a screen reader
    in its place, and neither is a decision an editor can make from a filename.
    The image is here so the two fields are answered against the thing they
    describe.

    This renders inside the action modal, which the page renders outside the `bkm`
    root, so the rules here are scoped to their own class rather than inherited from
    the page, and a package view still cannot reach the panel's utility classes.
--}}
@php
    use BasekitLaravel\BasekitLaravelMedia\Models\Media;
    use Illuminate\Support\Number;
@endphp

@if ($media instanceof Media)
    <div class="bkm-edit-preview">
        <style>
            .bkm-edit-preview {
                --bkm-preview-border: var(--gray-200, rgb(229 231 235));
                --bkm-preview-surface: var(--gray-50, rgb(249 250 251));
                --bkm-preview-muted: var(--gray-500, rgb(107 114 128));
            }

            .dark .bkm-edit-preview {
                --bkm-preview-border: var(--gray-700, rgb(55 65 81));
                --bkm-preview-surface: var(--gray-900, rgb(24 24 27));
                --bkm-preview-muted: var(--gray-400, rgb(156 163 175));
            }

            /* Contained rather than cropped: the editor is looking at the whole
               file here, not at the square the grid shows, and a cropped preview
               would show them a framing the site does not use. */
            .bkm-edit-preview-image {
                display: block;
                max-width: 100%;
                max-height: 18rem;
                margin-inline: auto;
                border: 1px solid var(--bkm-preview-border);
                border-radius: 0.5rem;
                background-color: var(--bkm-preview-surface);
                object-fit: contain;
            }

            .bkm-edit-preview-meta {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.375rem;
                margin-top: 0.5rem;
                color: var(--bkm-preview-muted);
                font-size: 0.75rem;
            }

            .bkm-edit-preview-meta-sep {
                opacity: 0.5;
            }
        </style>

        <img
            class="bkm-edit-preview-image"
            src="{{ $media->storageUrl() }}"
            alt="{{ $media->alt ?? $media->name }}"
        />

        <p class="bkm-edit-preview-meta">
            <span>{{ Number::fileSize($media->size) }}</span>

            @if (filled($media->mime_type))
                <span class="bkm-edit-preview-meta-sep">&middot;</span>

                <span>{{ $media->mime_type }}</span>
            @endif

            @if ($media->folder instanceof \BasekitLaravel\BasekitLaravelMedia\Models\MediaFolder)
                <span class="bkm-edit-preview-meta-sep">&middot;</span>

                <span>{{ $media->folder->name }}</span>
            @endif
        </p>
    </div>
@endif
