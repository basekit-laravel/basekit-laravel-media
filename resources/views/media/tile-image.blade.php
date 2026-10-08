{{--
    The image on a tile, shared by the version that opens the edit form and the
    one that does not.
--}}
<img
    class="bkm-tile__image"
    src="{{ $item->storageUrl() }}"
    alt="{{ $item->alt ?? $item->name }}"
    loading="lazy"
/>
