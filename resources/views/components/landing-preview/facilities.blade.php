@props(['site','labels'=>true])
<div class="facility-mosaic">
    @foreach($site['facilities']['spaces'] as $space)
    <button class="facility-tile" type="button" data-image="{{ $site['settings']['facilities_image'] }}" data-caption="{{ $space }} — Fasilitas SD Ceria Nusantara" data-facility-crop="{{ $loop->index % 3 }} {{ intdiv($loop->index, 3) }}" aria-label="Perbesar foto {{ $space }}">
        <span class="facility-tile-image" style="--crop-x:{{ ($loop->index % 3) * -100 }}%;--crop-y:{{ intdiv($loop->index, 3) * -100 }}%;--crop-focus-x:{{ (($loop->index % 3) * 2 + 1) * 16.6667 }}%;--crop-focus-y:{{ (intdiv($loop->index, 3) * 2 + 1) * 25 }}%">
            <img src="{{ $site['settings']['facilities_image'] }}" alt="" width="1536" height="1024" loading="lazy">
        </span>
        <span class="facility-tile-label">{{ $space }}</span>
    </button>
    @endforeach
</div>
