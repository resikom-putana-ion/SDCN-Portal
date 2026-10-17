@extends('layouts.app')
@section('content')
@php
$showAdminProfile = $screen === 'pengaturan' && request('tab') !== 'sekolah';
$fullAccess = \App\Support\SchoolAccess::hasFullAccess(auth()->user());
@endphp
@if($screen==='pengaturan')
<nav class="tabs" aria-label="Pengaturan sekolah">
    <a class="{{ $showAdminProfile ? 'active' : '' }}" href="/admin/pengaturan" @if($showAdminProfile) aria-current="page" @endif>Profil admin sekolah</a>
    @if($fullAccess)
    <a class="{{ !$showAdminProfile ? 'active' : '' }}" href="/admin/pengaturan?tab=sekolah" @if(!$showAdminProfile) aria-current="page" @endif>Profil sekolah</a>
    @endif
    <a href="/forgot-password">Keamanan</a>@if($fullAccess)<a href="/admin/alat">Integrasi</a>@endif<a href="/admin/bahasa">Preferensi</a>
</nav>
@endif
@if($showAdminProfile)
<x-card title="Profil admin sekolah">
<form method="post" action="/admin/profil-admin">@csrf
<div class="form-grid">
<label class="field">Nama admin<input name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="150" autocomplete="name"></label>
<label class="field">Email akun<input type="email" value="{{ auth()->user()->email }}" readonly></label>
<label class="field">Nomor HP pribadi<input type="tel" name="phone" value="{{ old('phone', auth()->user()->phone ?? '') }}" maxlength="30" autocomplete="tel" placeholder="08... atau +62..."><small class="muted">Nomor kontak pribadi, belum diverifikasi melalui SMS.</small></label>
</div>
<p class="muted">Hanya Anda yang dapat mengubah nama, nomor HP, dan foto profil ini. Email login tetap.</p>
<x-student-photo :admin="true"/>
<div class="actions"><button class="btn">Simpan profil admin</button></div>
</form>
</x-card>
@else
<x-card :title="$screen==='pengaturan'?'Identitas SD Ceria Nusantara':'Informasi '.mb_strtolower($titleForm)"><form method="post" action="/admin/record/{{ $kind }}{{ $record?'/'.$record->id:'' }}">@csrf<x-fields :fields="$fields" :values="$record?->data ?? []" :live-nominal="$kind === 'invoices'" :capital-name="in_array($kind, ['students', 'staff'])"/>@if(in_array($kind, ['students', 'staff']))<x-student-photo :record="$record" :staff="$kind === 'staff'"/>@endif
@if($kind==='settings')<p class="muted" style="margin-bottom:24px">Kontak dan alamat disalin ke draf website. Tinjau dan terbitkan agar perubahan tampil pada website sekolah.</p>@endif<div class="actions"><button class="btn">{{ __('Simpan perubahan') }}</button><a class="btn secondary" href="/admin/{{ $screen }}{{ $screen === 'pengaturan' ? '?tab=sekolah' : '' }}">{{ __('Batal') }}</a></div></form></x-card>
@endif
@endsection
