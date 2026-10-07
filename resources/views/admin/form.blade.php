@extends('layouts.app')
@section('content')
@if($screen==='pengaturan')<div class="tabs"><a class="active" href="/admin/pengaturan">Profil sekolah</a><a href="/forgot-password">Keamanan</a><a href="/admin/alat">Integrasi</a><a href="/admin/bahasa">Preferensi</a></div>@endif
<x-card :title="$screen==='pengaturan'?'Identitas SD Ceria Nusantara':'Informasi '.mb_strtolower($titleForm)"><form method="post" action="/admin/record/{{ $kind }}{{ $record?'/'.$record->id:'' }}">@csrf<x-fields :fields="$fields" :values="$record?->data ?? []"/>@if($kind==='settings')<p class="muted" style="margin-bottom:24px">Kontak dan alamat ini ditampilkan pada website sekolah.</p>@endif<div class="actions"><button class="btn">{{ __('Simpan perubahan') }}</button><a class="btn secondary" href="/admin/{{ $screen }}">{{ __('Batal') }}</a></div></form></x-card>
@endsection
