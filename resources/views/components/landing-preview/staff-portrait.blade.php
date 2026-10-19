@props(['index' => 0, 'name' => 'Profil anggota tim', 'photo' => null])
@php($safePhoto = is_string($photo) && \App\Services\LandingPreview::validPhoto($photo) ? $photo : null)
<span {{ $attributes->class(['staff-portrait']) }} role="img" aria-label="Foto profil {{ $name }}" @if($safePhoto) style="background-image: url('{{ $safePhoto }}'); background-size: cover; background-position: center;" @else style="--portrait-x: {{ ($index % 5) * 25 }}%; --portrait-y: {{ intdiv($index, 5) * 20 }}%" @endif></span>
