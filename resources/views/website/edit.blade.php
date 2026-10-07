@extends('layouts.app')
@section('content')
@include('website.toolbar')
<div class="tabs">@foreach(['hero'=>'Hero','profil'=>'Profil','program'=>'Program','fasilitas'=>'Fasilitas','guru'=>'Guru','kontak'=>'Kontak'] as $key=>$label)<a class="{{ $section===$key?'active':'' }}" href="/admin/website/{{ $key }}/edit">{{ $label }}</a>@endforeach</div>
<form method="post" action="/admin/website/{{ $section }}/edit">@csrf
<div class="{{ $section==='hero'?'grid-2':'' }}"><x-card :title="$definition['heading']">
@if($section==='ppdb')@php
$period=\App\Support\School::period();
@endphp<div class="form-grid">@foreach(['period'=>'Periode pendaftaran','year'=>'Tahun ajaran penerimaan','age'=>'Usia masuk','fee'=>'Biaya pendaftaran'] as $key=>$label)<label class="field">{{ $label }}<input value="{{ $key==='fee'?\App\Support\School::money($period[$key]):$period[$key] }}" readonly></label>@endforeach</div>@endif
<x-fields :fields="$definition['fields']" :values="$record->value('draft')"/>
@if(in_array($section,['hero','fasilitas']))<a class="btn secondary" href="/admin/media" target="_blank">Kelola gambar di media</a>@endif
@if($section==='guru')<x-notice>Hanya nama, foto, peran publik dan bio yang dipilih untuk website. Data pribadi tetap di profil internal.</x-notice>@endif
@if($section==='ppdb')<x-notice>Periode dan biaya mengacu pada pengaturan PPDB, sehingga website dan Admin menampilkan informasi yang sama.</x-notice><a class="btn secondary" href="/admin/periode">Atur periode & kuota</a>@endif
</x-card>
@if($section==='hero')<x-card title="Pratinjau bagian Hero"><img class="content-image" src="/assets/{{ $record->value('draft.image','classroom.jpeg') }}" alt="Ruang belajar SD Ceria Nusantara"><small class="eyebrow">{{ $record->value('draft.eyebrow') }}</small><h2 style="margin:16px 0">{{ $record->value('draft.title') }}</h2><p class="muted">{{ $record->value('draft.description') }}</p><div class="actions" style="margin-top:20px"><a class="btn" href="/ppdb" target="_blank">{{ $record->value('draft.button') }}</a><a class="btn secondary" href="/admin/website/preview">Profil Sekolah</a></div></x-card>@endif</div>
@if($section==='fasilitas')<div class="media-grid" style="margin-bottom:24px">@foreach(['image','image2','image3'] as $key)<div class="media-tile"><img src="/assets/{{ $record->value('draft.'.$key,'classroom.jpeg') }}" alt="{{ $record->value('draft.alt') }}"></div>@endforeach</div>@endif
<div class="actions"><button class="btn">Simpan draf</button><a class="btn secondary" href="/admin/website/preview">Pratinjau</a><a class="btn white" href="/admin/halaman">Batal</a></div></form>
@endsection
