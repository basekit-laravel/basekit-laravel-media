{{--
    The image on a tile, shared by the version that opens the edit form and the
    one that does not, so a user who may only look at the library sees the same
    picture as everyone else.
--}}
<img
    class="bkm-tile-image"
    src="{{ $item->storageUrl() }}"
    alt="{{ $item->alt ?? $item->name }}"
    loading="lazy"
/>