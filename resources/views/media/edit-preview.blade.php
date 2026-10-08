{{--
    The file being edited, shown above the form so the name and alt text are
    answered against what they describe.

    Rendered inside the action modal, outside the `bkm-library` root, so its
    rules are scoped to their own class.
--}}
@if ($media !== null)
    <div class="bkm-edit-preview">

        <img
            class="bkm-edit-preview__image"
            src="{{ $media->storageUrl() }}"
            alt="{{ $media->alt ?? $media->name }}"
        />

        <p class="bkm-edit-preview__meta">
            <span>{{ $media->humanSize() }}</span>

            @if (filled($media->mime_type))
                <span class="bkm-edit-preview__meta-sep">&middot;</span>

                <span>{{ $media->mime_type }}</span>
            @endif

            @if ($media->folder !== null)
                <span class="bkm-edit-preview__meta-sep">&middot;</span>

                <span>{{ $media->folder->name }}</span>
            @endif
        </p>
    </div>
@endif
