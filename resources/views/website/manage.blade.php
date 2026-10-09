@extends('layouts.app')
@section('content')
@include('website.toolbar')
@php
$content=app(\App\Services\WebsiteContent::class)->all();
$drafts=collect(\App\Support\CloudData::collection('dashboard_content_drafts'))->keyBy('id');
$rows=collect(\App\Services\WebsiteContent::SECTIONS)->map(fn($name,$key)=>['name'=>$name,'summary'=>$content[$key]['title']??$content[$key]['school_name']??'', 'date'=>$drafts->get($key)['updated_at']??'?','author'=>$drafts->get($key)['author']??'?','status'=>$drafts->has($key)?'Draf':'Terbit','_url'=>'/admin/website/'.$key.'/edit']);
@endphp
@if(in_array($screen,['website','halaman']))
@if($screen==='website')<x-stats :items="[[count($content),'Bagian website','globe'],[$drafts->count(),'Draf untuk ditinjau','file'],[\App\Models\SchoolRecord::ofKind('applicants')->count(),'Pendaftar online','users']]"/>
<x-card title="Landing page sekolah"><h1>{{ $content['home']['title']??'Website sekolah' }}</h1><p class="muted">{{ $content['home']['description']??'' }}</p><div class="actions"><a href="{{ config('school.landing_url') }}" class="btn secondary" target="_blank" rel="noopener">Buka landing page</a><a href="/admin/website/home/edit" class="btn">Edit beranda</a></div></x-card>@endif
<x-table :columns="['name'=>'Bagian website','summary'=>'Isi utama','date'=>'Draf diperbarui','status'=>'Status']" :rows="$rows"/>
@elseif($screen==='publikasi')
<x-table :columns="['name'=>'Bagian','summary'=>'Isi','author'=>'Editor','status'=>'Status']" :rows="$rows->where('status','Draf')"/>
<form method="post" action="/admin/website/publish" data-confirm="Terbitkan draf ke landing page sekolah?">@csrf
<x-card title="Checklist publikasi">@foreach(['text'=>'Judul dan teks sudah diperiksa','image'=>'Gambar sudah diperiksa','contact'=>'Kontak sekolah sudah sesuai','period'=>'Periode dan biaya PPDB sudah sesuai','offline'=>'Alur penerimaan sudah jelas'] as $key=>$label)<label class="check-row"><input type="checkbox" name="checks[]" value="{{ $key }}" required>{{ $label }}</label>@endforeach
<label class="field">Catatan publikasi<textarea name="notes" rows="3" required>{{ old('notes') }}</textarea></label></x-card>
<div class="actions"><a class="btn secondary" href="/admin/website/preview">Pratinjau website</a><button class="btn" @disabled($drafts->isEmpty())>Terbitkan perubahan</button></div></form>
@elseif($screen==='media')
@php($media=collect(\App\Support\CloudData::collection('uploaded_files'))->filter(fn($file)=>str_starts_with($file['path'],'media/')))
<div class="media-grid">@foreach($media as $file)<div class="media-tile"><img src="/{{ $file['path'] }}" alt="Media website"><span>/{{ $file['path'] }}</span></div>@endforeach</div>
<x-card title="Unggah media"><form method="post" action="/admin/website/media" enctype="multipart/form-data">@csrf<div class="form-grid"><label class="field">Gambar<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required><small>Maksimal 1 MB.</small></label><label class="field">Keterangan<input name="alt" required maxlength="300"></label></div><button class="btn">Unggah media</button></form></x-card>
@elseif($screen==='terbit')<x-card title="Website berhasil diperbarui"><p>Perubahan telah diterbitkan ke landing page sekolah.</p><div class="actions"><a href="{{ config('school.landing_url') }}" class="btn" target="_blank" rel="noopener">Buka landing page</a><a href="/admin/halaman" class="btn secondary">Kelola konten</a></div></x-card>@endif
@endsection
