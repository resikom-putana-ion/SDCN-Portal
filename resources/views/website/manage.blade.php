@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css">
@include('website.toolbar')
@php
$content=app(\App\Services\WebsiteContent::class)->all(true);
$drafts=collect(\App\Support\CloudData::collection('dashboard_content_drafts'))->keyBy('id');
$rows=collect(\App\Services\WebsiteContent::SECTIONS)->map(fn($name,$key)=>['name'=>$name,'summary'=>$content[$key]['title']??$content[$key]['school_name']??'', 'date'=>$drafts->get($key)['updated_at']??'?','author'=>$drafts->get($key)['author']??'?','status'=>$drafts->has($key)?'Draf':'Terbit','_url'=>'/admin/website/'.$key.'/edit']);
@endphp
@if(in_array($screen,['website','halaman']))
<div class="cms">
<div class="cms-topline"><div><span class="cms-eyebrow">KONTEN SEKOLAH</span><h2>Halaman yang ingin Anda ubah</h2><p>Pilih halaman, klik bagiannya, lalu edit dari pratinjau landing page.</p></div><a class="btn secondary" href="{{ config('school.landing_url') }}" target="_blank" rel="noopener">Buka website publik</a></div>
<div class="cms-guide"><span>01 &middot; Pilih & edit<small>Susunan mengikuti halaman website.</small></span><span>02 &middot; Periksa pratinjau<small>Coba desktop, tablet, dan ponsel.</small></span><span>03 &middot; Terbitkan<small>Draf diperiksa sebelum tampil ke publik.</small></span></div>
<div class="cms-page-grid">
@foreach(\App\Services\LandingPreview::PAGES + ['settings'=>'Informasi sekolah & PPDB'] as $key=>$label)
@php($pageData=$content[$key]??[])
<article class="cms-page-card"><div class="cms-page-card-inner"><div class="cms-page-cover"><img src="{{ \App\Services\LandingPreview::asset($pageData['image']??$pageData['logo']??$content['home']['image']??'') }}" alt="" loading="lazy"><x-badge :value="$drafts->has($key)?'Draf':'Terbit'"/></div><div class="cms-page-copy"><h3>{{ $label }}</h3><p>{{ \Illuminate\Support\Str::limit($pageData['directory_description']??$pageData['description']??$pageData['tagline']??'Kelola informasi yang tampil di website sekolah.',120) }}</p><div class="actions"><a class="btn" href="/admin/website/{{ $key }}/edit">Edit halaman</a><a class="btn secondary" href="/admin/website/preview?page={{ $key==='settings'?'home':$key }}">Pratinjau</a></div></div></div></article>
@endforeach
</div></div>
@elseif($screen==='publikasi')
<x-table :columns="['name'=>'Bagian','summary'=>'Isi','author'=>'Editor','status'=>'Status']" :rows="$rows->where('status','Draf')"/>
<form method="post" action="/admin/website/publish" data-confirm="Terbitkan draf ke landing page sekolah?">@csrf
<x-card title="Checklist publikasi">@foreach(['text'=>'Judul dan teks sudah diperiksa','image'=>'Gambar sudah diperiksa','contact'=>'Kontak sekolah sudah sesuai','period'=>'Periode dan biaya PPDB sudah sesuai','offline'=>'Alur penerimaan sudah jelas'] as $key=>$label)<label class="check-row"><input type="checkbox" name="checks[]" value="{{ $key }}" required>{{ $label }}</label>@endforeach
<label class="field">Catatan publikasi<textarea name="notes" rows="3" required>{{ old('notes') }}</textarea></label></x-card>
<div class="actions"><a class="btn secondary" href="/admin/website/preview">Pratinjau website</a><button class="btn" @disabled($drafts->isEmpty())>Terbitkan perubahan</button></div></form>
@elseif($screen==='media')
@php($media=collect(\App\Support\CloudData::collection('uploaded_files'))->filter(fn($file)=>str_starts_with($file['path'],'media/')))
<div class="media-grid">@foreach($media as $file)<div class="media-tile"><img src="/{{ $file['path'] }}" alt="Media website"><span>/{{ $file['path'] }}</span></div>@endforeach</div>
<x-card title="Unggah media"><form method="post" action="/admin/website/media" enctype="multipart/form-data">@csrf<div class="form-grid"><label class="field" data-crop-field>Gambar<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" data-crop-input required><small>Maksimal 5 MB. Atur potongan pada grid sebelum mengunggah.</small></label><label class="field">Keterangan<input name="alt" required maxlength="300"></label></div><button class="btn">Unggah media</button></form></x-card>
@elseif($screen==='terbit')<x-card title="Website berhasil diperbarui"><p>Perubahan telah diterbitkan ke landing page sekolah.</p><div class="actions"><a href="{{ config('school.landing_url') }}" class="btn" target="_blank" rel="noopener">Buka landing page</a><a href="/admin/halaman" class="btn secondary">Kelola konten</a></div></x-card>@endif
@endsection
