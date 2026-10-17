@props(['index' => 0, 'name' => 'Profil anggota tim', 'photo' => null])
<span {{ $attributes->class(['staff-portrait']) }} role="img" aria-label="Foto {{ $name }}" @if($photo) style="background-image: url('{{ $photo }}'); background-size: cover; background-position: center;" @else style="--portrait-x: {{ ($index % 5) * 25 }}%; --portrait-y: {{ intdiv($index, 5) * 20 }}%" @endif></span>
