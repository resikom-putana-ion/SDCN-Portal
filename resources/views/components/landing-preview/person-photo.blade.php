@props(['person'])
@php
    $photo = $person['photo'] ?? null;
    $safePhoto = is_string($photo) && \App\Services\LandingPreview::validPhoto($photo) ? $photo : null;
    $portrait = (int) ($person['portrait'] ?? 0);
@endphp
<span class="website-person-photo" role="img" aria-label="Foto {{ $person['name'] }}" @if($safePhoto) style="background-image:url('{{ $safePhoto }}');background-size:cover;background-position:center" @else style="--portrait-x:{{ ($portrait % 5) * 25 }}%;--portrait-y:{{ intdiv($portrait, 5) * 20 }}%" @endif></span>
