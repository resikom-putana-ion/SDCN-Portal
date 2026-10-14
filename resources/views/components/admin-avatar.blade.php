@php
$admin = auth()->user();
$initials = collect(preg_split('/\s+/u', trim($admin->name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
@if(($admin->photo_path ?? null))
<img class="avatar" src="/admin/foto-admin" alt="Foto profil {{ $admin->name }}">
@else
<span class="avatar" aria-label="{{ $admin->name }}">{{ mb_strtoupper($initials) }}</span>
@endif
