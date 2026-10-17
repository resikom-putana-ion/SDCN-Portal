@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css"><script src="/js/website-editor.js" defer></script>
@php
$previewPages=\App\Services\LandingPreview::PAGES + ['privacy'=>'Privasi','terms'=>'Ketentuan'];
$previewPage=is_string(request('page')) && isset($previewPages[request('page')]) ? request('page') : 'home';
$editPage=in_array($previewPage,['privacy','terms'])?'settings':$previewPage;
@endphp
<div class="cms" data-website-preview>
    <div class="cms-topline"><div><span class="cms-eyebrow">PRATINJAU DRAF</span><h2>Lihat seperti pengunjung sekolah</h2><p>Periksa tampilan sebelum menerbitkan perubahan.</p></div><div class="actions"><a class="btn secondary" href="/admin/website/{{ $editPage }}/edit">Kembali mengedit</a><a class="btn" href="/admin/publikasi">Tinjau & terbitkan</a></div></div>
    <nav class="cms-pages" aria-label="Halaman pratinjau">@foreach($previewPages as $key=>$label)<a href="?page={{ $key }}" @if($key===$previewPage) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <section class="cms-stage cms-stage-full"><div class="cms-stage-toolbar"><span><i></i> Pratinjau draf</span><div class="cms-device" role="group" aria-label="Ukuran pratinjau"><button type="button" data-cms-width="1280" aria-pressed="true">Desktop</button><button type="button" data-cms-width="768" aria-pressed="false">Tablet</button><button type="button" data-cms-width="390" aria-pressed="false">Ponsel</button></div></div><div class="cms-frame-wrap" data-cms-frame-wrap><iframe title="Pratinjau website sekolah" data-cms-frame src="/admin/website/preview/frame?page={{ $previewPage }}" sandbox="allow-scripts allow-same-origin"></iframe></div></section>
</div>
@endsection
