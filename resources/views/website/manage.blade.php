@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css?v=20261018">
@include('website.toolbar')
@php
$content=app(\App\Services\WebsiteContent::class)->all(true);
$drafts=collect(\App\Support\CloudData::collection('dashboard_content_drafts'))->keyBy('id');
$rows=collect(\App\Services\WebsiteContent::SECTIONS)->map(fn($name,$key)=>['name'=>$name,'summary'=>$content[$key]['title']??$content[$key]['school_name']??'', 'date'=>$drafts->get($key)['updated_at']??'?','author'=>$drafts->get($key)['author']??'?','status'=>$drafts->has($key)?'Draf':'Terbit','_url'=>'/admin/website/'.$key.'/edit']);
@endphp
@if(in_array($screen,['website','halaman']))
<div class="cms">
<div class="cms-topline"><div><span class="cms-eyebrow">KONTEN SEKOLAH</span><h2>Kelola halaman website</h2><p>Pilih halaman untuk mengubah teks, gambar, dan susunan isinya.</p></div><a class="btn secondary" href="{{ config('school.landing_url') }}" target="_blank" rel="noopener">Buka website publik ↗</a></div>
@if($drafts->isNotEmpty())<div class="cms-draft-notice"><div><strong>{{ $drafts->count() }} halaman memiliki draf tersimpan</strong><p>Perubahan belum tampil di website publik.</p></div><a class="btn" href="/admin/publikasi">Tinjau perubahan</a></div>@endif
<div class="cms-page-grid">
@foreach(\App\Services\LandingPreview::PAGES + ['settings'=>'Informasi sekolah & PPDB'] as $key=>$label)
@php($pageData=$content[$key]??[])
<article class="cms-page-card"><div class="cms-page-card-inner"><div class="cms-page-cover"><img src="{{ \App\Services\LandingPreview::asset($pageData['image']??$pageData['logo']??$content['home']['image']??'') }}" alt="" loading="lazy"><x-badge :value="$drafts->has($key)?'Draf':'Terbit'"/></div><div class="cms-page-copy"><h3>{{ $label }}</h3><p>{{ \Illuminate\Support\Str::limit($pageData['directory_description']??$pageData['description']??$pageData['tagline']??'Kelola informasi yang tampil di website sekolah.',120) }}</p><div class="actions"><a class="btn" href="/admin/website/{{ $key }}/edit">Edit halaman</a><a class="btn secondary" href="/admin/website/preview?page={{ $key==='settings'?'home':$key }}">Pratinjau</a></div></div></div></article>
@endforeach
</div></div>
@elseif($screen==='publikasi')
@include('website.publication')
@elseif($screen==='media')
@php($media=collect(\App\Support\CloudData::collection('uploaded_files'))->filter(fn($file)=>str_starts_with($file['path'],'media/')))
<div class="media-grid">@foreach($media as $file)<div class="media-tile"><img src="/{{ $file['path'] }}" alt="Media website"><span>/{{ $file['path'] }}</span></div>@endforeach</div>
<x-card title="Unggah media"><form method="post" action="/admin/website/media" enctype="multipart/form-data">@csrf<div class="form-grid"><label class="field" data-crop-field>Gambar<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" data-crop-input required><small>Maksimal 5 MB. Atur potongan pada grid sebelum mengunggah.</small></label><label class="field">Keterangan<input name="alt" required maxlength="300"></label></div><button class="btn">Unggah media</button></form></x-card>
@elseif($screen==='terbit')
@php($publication=\App\Support\CloudData::store()->get('dashboard_runtime','website_publication'))
<div class="cms"><div class="cms-empty"><span class="cms-empty-icon" aria-hidden="true">✓</span><h2>{{ $publication ? 'Perubahan berhasil diterbitkan' : 'Belum ada hasil publikasi' }}</h2>
@if($publication)<p>{{ $publication['change_count'] }} isian pada {{ count($publication['pages']) }} halaman telah diperbarui oleh {{ $publication['author'] }}.</p><p>{{ implode(' · ', $publication['pages']) }}</p><p>{{ \Carbon\Carbon::parse($publication['published_at'])->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB</p>@else<p>Simpan dan tinjau draf sebelum menerbitkan perubahan website.</p>@endif
<div class="actions"><a href="{{ config('school.landing_url') }}" class="btn" target="_blank" rel="noopener">Buka website publik ↗</a><a href="/admin/website" class="btn secondary">Kelola halaman</a></div></div></div>
@endif
@endsection
