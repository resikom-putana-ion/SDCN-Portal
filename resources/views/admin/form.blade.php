@extends('layouts.app')
@section('content')
@if($screen==='pengaturan')<div class="tabs"><a class="active" href="/admin/pengaturan">Profil sekolah</a><a href="/forgot-password">Keamanan</a><a href="/admin/alat">Integrasi</a><a href="/admin/bahasa">Preferensi</a></div>@endif
@if($screen==='pengaturan')
<x-card title="Profil admin sekolah">
<form method="post" action="/admin/profil-admin">@csrf
<div class="form-grid">
<label class="field">Nama admin<input name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="150" autocomplete="name"></label>
<label class="field">Email akun<input type="email" value="{{ auth()->user()->email }}" readonly></label>
</div>
<x-student-photo :admin="true"/>
<div class="actions"><button class="btn">Simpan profil admin</button></div>
</form>
</x-card>
@endif
<x-card :title="$screen==='pengaturan'?'Identitas SD Ceria Nusantara':'Informasi '.mb_strtolower($titleForm)"><form method="post" action="/admin/record/{{ $kind }}{{ $record?'/'.$record->id:'' }}">@csrf<x-fields :fields="$fields" :values="$record?->data ?? []" :live-nominal="$kind === 'invoices'" :capital-name="in_array($kind, ['students', 'staff'])"/>@if(in_array($kind, ['students', 'staff']))<x-student-photo :record="$record" :staff="$kind === 'staff'"/>@endif
@if($kind==='settings')<p class="muted" style="margin-bottom:24px">Kontak dan alamat disalin ke draf website. Tinjau dan terbitkan agar perubahan tampil pada website sekolah.</p>@endif<div class="actions"><button class="btn">{{ __('Simpan perubahan') }}</button><a class="btn secondary" href="/admin/{{ $screen }}">{{ __('Batal') }}</a></div></form></x-card>
@endsection
