@extends('layouts.app')
@section('content')
@include('website.toolbar')
<div class="tabs">@foreach(['hero'=>['Hero','users'],'profil'=>['Profil','calendar'],'program'=>['Program','wallet'],'fasilitas'=>['Fasilitas','book'],'guru'=>['Guru','file'],'kontak'=>['Kontak','settings']] as $key=>[$label,$icon])<a class="{{ $section===$key?'active':'' }}" href="/admin/website/{{ $key }}/edit"><x-icon :name="$icon"/>{{ $label }}</a>@endforeach</div>
<form method="post" action="/admin/website/{{ $section }}/edit">@csrf
<div class="{{ $section==='hero'?'grid-2 hero-editor':'' }}"><x-card :title="$definition['heading']">
@if($section==='ppdb')@php
$period=\App\Support\School::period();
@endphp<div class="form-grid">@foreach(['period'=>'Periode pendaftaran','year'=>'Tahun ajaran penerimaan','age'=>'Usia masuk','fee'=>'Biaya pendaftaran'] as $key=>$label)<label class="field">{{ $label }}<input value="{{ $key==='fee'?\App\Support\School::money($period[$key]):$period[$key] }}" readonly></label>@endforeach</div>@endif
<x-fields :fields="$section==='hero'?array_values(array_filter($definition['fields'],fn($f)=>$f['name']!=='image')):$definition['fields']" :values="$record->value('draft')"/>
@if($section==='hero')<input type="hidden" name="image" value="{{ old('image',$record->value('draft.image')) }}">@endif
@if($section==='fasilitas')<a class="btn secondary" href="/admin/media" target="_blank">Kelola gambar di media</a>@endif
@if($section==='guru')<x-notice>Hanya nama, foto, peran publik dan bio yang dipilih untuk website. Data pribadi tetap di profil internal.</x-notice>@endif
@if($section==='ppdb')<x-notice>Periode dan biaya mengacu pada pengaturan PPDB, sehingga website dan Admin menampilkan informasi yang sama.</x-notice><a class="btn secondary" href="/admin/periode">Atur periode & kuota</a>@endif
</x-card>
@if($section==='hero')<x-card title="Pratinjau bagian Hero"><div class="hero-preview-panel"><small class="eyebrow" data-preview-target="eyebrow">{{ $record->value('draft.eyebrow') }}</small><h2 data-preview-target="title">{{ $record->value('draft.title') }}</h2><p data-preview-target="description">{{ $record->value('draft.description') }}</p><img src="/assets/{{ $record->value('draft.image','classroom.jpeg') }}" alt="Ruang belajar SD Ceria Nusantara" data-hero-image><div class="actions"><a class="btn yellow" href="/ppdb" target="_blank" data-preview-target="button">{{ $record->value('draft.button') }}</a><a class="btn secondary" href="/admin/website/preview" data-preview-target="secondary">Profil Sekolah</a></div></div><small class="muted" style="display:block;margin-top:16px">Gambar hero · Ruang belajar SD Ceria Nusantara</small></x-card>@endif</div>
@if($section==='fasilitas')<div class="media-grid" style="margin-bottom:24px">@foreach(['image','image2','image3'] as $key)<div class="media-tile"><img src="/assets/{{ $record->value('draft.'.$key,'classroom.jpeg') }}" alt="{{ $record->value('draft.alt') }}"></div>@endforeach</div>@endif
<div class="actions"><button class="btn">Simpan draf</button>@if($section==='hero')<button type="button" class="btn secondary" data-open-media>Pilih gambar</button>@else<a class="btn secondary" href="/admin/website/preview">Pratinjau</a>@endif<a class="btn white" href="/admin/halaman">Batal</a></div></form>
@if($section==='hero')<dialog id="media-picker"><h2>Pilih gambar Hero</h2><div class="media-grid" style="margin:24px 0">@foreach(collect([['name'=>'Ruang kelas','image'=>'classroom.jpeg']])->concat(\App\Models\SchoolRecord::ofKind('media')->get()->map(fn($r)=>$r->data)) as $media)<button class="media-tile" type="button" data-select-media="{{ $media['image'] }}" data-target="image"><img src="/assets/{{ $media['image'] }}" alt="{{ $media['name'] }}"><span>{{ $media['name'] }}</span></button>@endforeach</div><a class="btn secondary" href="/admin/media" target="_blank">Unggah media baru</a><button type="button" class="btn white" data-close-media>Batal</button></dialog>@endif
@endsection
