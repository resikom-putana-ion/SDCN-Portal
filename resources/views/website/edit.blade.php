@extends('layouts.app')
@section('content')
@include('website.toolbar')
<div class="tabs">@foreach(\App\Services\WebsiteContent::SECTIONS as $key=>$label)<a class="{{ $section===$key?'active':'' }}" href="/admin/website/{{ $key }}/edit">{{ $label }}</a>@endforeach</div>
<form method="post" action="/admin/website/{{ $section }}/edit" enctype="multipart/form-data">@csrf
<input type="hidden" name="version" value="{{ $version }}">
<x-card :title="\App\Services\WebsiteContent::SECTIONS[$section]">
<p class="muted" style="margin-bottom:24px">Isi mengikuti data website saat ini. Simpan sebagai draf, lalu terbitkan setelah ditinjau.</p>
<div class="form-grid">
@foreach($fields as $key=>$value)
@if(!is_array($value))
@php($imageField=(bool)preg_match('/(^image$|_image$|^logo$|\.photo$)/',$key))
<label class="field">{{ \App\Services\ContentLabels::label($key) }}
@if($imageField)
<img src="{{ \App\Services\WebsiteContent::image((string)$value) }}" alt="{{ \App\Services\ContentLabels::label($key) }}" style="max-width:260px;height:150px;object-fit:cover;border-radius:12px">
<input name="values[{{ $key }}]" value="{{ old('values')[$key] ?? $value }}" aria-label="Lokasi gambar {{ \App\Services\ContentLabels::label($key) }}">
<input type="file" name="uploads[{{ $loop->index }}]" accept=".jpg,.jpeg,.png,.webp"><small>Unggah gambar pengganti, maksimal 1 MB.</small>
@elseif(is_bool($value))<select name="values[{{ $key }}]"><option value="1" @selected($value)>Ya</option><option value="0" @selected(!$value)>Tidak</option></select>
@elseif(mb_strlen((string)$value)>100 || str_contains((string)$value,"\n"))<textarea name="values[{{ $key }}]" rows="4" maxlength="10000">{{ old('values')[$key] ?? $value }}</textarea>
@else<input name="values[{{ $key }}]" value="{{ old('values')[$key] ?? $value }}" maxlength="10000">
@endif
</label>
@endif
@endforeach
</div>
</x-card><div class="actions"><button class="btn">Simpan draf</button><a href="/admin/website/preview" class="btn secondary">Pratinjau draf</a><a href="/admin/halaman" class="btn white">Kembali</a></div>
</form>
@endsection
