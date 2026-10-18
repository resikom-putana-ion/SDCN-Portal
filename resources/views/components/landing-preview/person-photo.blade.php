@props(['person'])
@php
    $photo = $person['photo'] ?? null;
    $safePhoto = is_string($photo) && preg_match('#^/(?:(?:landing-preview/)?assets/(?:design|team)|media)/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$#', $photo) ? $photo : null;
    if (is_string($photo) && preg_match('#^data:image/(?:jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $photo)) $safePhoto = $photo;
    $portrait = (int) ($person['portrait'] ?? 0);
@endphp
<span class="website-person-photo" role="img" aria-label="Foto {{ $person['name'] }}" @if($safePhoto) style="background-image:url('{{ $safePhoto }}');background-size:cover;background-position:center" @else style="--portrait-x:{{ ($portrait % 5) * 25 }}%;--portrait-y:{{ intdiv($portrait, 5) * 20 }}%" @endif></span>
