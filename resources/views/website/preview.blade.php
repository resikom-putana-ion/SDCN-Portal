@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css?v=20261018"><script src="/js/website-editor.js?v=20261018" defer></script>
@php
$previewPages=\App\Services\LandingPreview::PAGES + ['privacy'=>'Privasi','terms'=>'Ketentuan'];
$previewPage=is_string(request('page')) && isset($previewPages[request('page')]) ? request('page') : 'home';
$editPage=in_array($previewPage,['privacy','terms'])?'settings':$previewPage;
$previewState=request('state')==='published'?'published':'draft';
@endphp
<div class="cms" data-website-preview>
    <div class="cms-topline"><div><span class="cms-eyebrow">PRATINJAU DRAF</span><h2>Lihat seperti pengunjung sekolah</h2><p>Periksa tampilan sebelum menerbitkan perubahan.</p></div><div class="actions"><a class="btn secondary" href="/admin/website/{{ $editPage }}/edit">Kembali mengedit</a><a class="btn" href="/admin/publikasi">Tinjau & terbitkan</a></div></div>
    <nav class="cms-pages" aria-label="Versi pratinjau"><a href="?page={{ $previewPage }}&state=draft" @if($previewState==='draft') aria-current="page" @endif>Draf tersimpan</a><a href="?page={{ $previewPage }}&state=published" @if($previewState==='published') aria-current="page" @endif>Sedang tayang</a></nav>
    <nav class="cms-pages" aria-label="Halaman pratinjau">@foreach($previewPages as $key=>$label)<a href="?page={{ $key }}&state={{ $previewState }}" @if($key===$previewPage) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <section class="cms-stage cms-stage-full"><div class="cms-stage-toolbar"><span><i></i> {{ $previewState==='published' ? 'Sedang tayang' : 'Draf tersimpan' }}</span><div class="cms-device" role="group" aria-label="Ukuran pratinjau"><button type="button" data-cms-width="1280" aria-pressed="true">Desktop</button><button type="button" data-cms-width="768" aria-pressed="false">Tablet</button><button type="button" data-cms-width="390" aria-pressed="false">Ponsel</button></div></div><div class="cms-frame-wrap" data-cms-frame-wrap><iframe title="Pratinjau website sekolah" data-cms-frame src="/admin/website/preview/frame?page={{ $previewPage }}&state={{ $previewState }}" sandbox="allow-scripts allow-same-origin"></iframe></div></section>
</div>
@endsection
