@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css">
<script src="/js/website-editor.js" defer></script>
@php
    $selected = request('block');
    if (!is_string($selected) || !isset($blocks[$selected])) $selected = array_key_first($blocks);
    $previewPage = $section === 'settings' ? 'home' : $section;
    if ($section === 'settings' && $selected === 'legal') $previewPage = 'privacy';
    if ($section === 'teachers' && $selected === 'team') $previewPage = 'home';
@endphp
<div class="cms" data-website-editor data-section="{{ $section }}" data-initial-block="{{ $selected }}" data-preview-page="{{ $previewPage }}">
    <div class="cms-topline">
        <div><span class="cms-eyebrow">WEBSITE SEKOLAH</span><h2>Edit dari tampilan halaman</h2><p>Pilih bagian pada pratinjau, lalu ubah isinya di panel kanan.</p></div>
        <div class="actions"><a class="btn secondary" href="/admin/website/preview?page={{ $previewPage }}" target="_blank" rel="noopener">Pratinjau penuh</a><a class="btn" href="/admin/publikasi">Tinjau & terbitkan</a></div>
    </div>
    <nav class="cms-pages" aria-label="Halaman website">
        @foreach(\App\Services\LandingPreview::PAGES + ['settings'=>'Informasi sekolah'] as $key=>$label)
        <a href="/admin/website/{{ $key }}/edit" @if($key===$section) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="cms-workspace">
        <aside class="cms-outline"><span class="cms-eyebrow">SUSUNAN HALAMAN</span><p>Dari atas ke bawah</p>
            <nav aria-label="Bagian halaman">@foreach($blocks as $id=>$block)<a href="?block={{ $id }}" data-cms-select="{{ $id }}" @if($id===$selected) aria-current="true" @endif><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span>{{ $block['label'] }}</a>@endforeach</nav>
            <a class="cms-back" href="/admin/website">Semua halaman</a>
        </aside>
        <section class="cms-stage" aria-label="Pratinjau interaktif">
            <div class="cms-stage-toolbar"><span><i></i> Pratinjau draf</span><div class="cms-device" role="group" aria-label="Ukuran pratinjau"><button type="button" data-cms-width="1280" aria-pressed="true">Desktop</button><button type="button" data-cms-width="768" aria-pressed="false">Tablet</button><button type="button" data-cms-width="390" aria-pressed="false">Ponsel</button></div></div>
            <div class="cms-frame-wrap" data-cms-frame-wrap><iframe title="Pratinjau landing page yang dapat diedit" data-cms-frame src="/admin/website/preview/frame?page={{ $previewPage }}&edit=1" sandbox="allow-scripts allow-same-origin"></iframe></div>
            <p class="cms-stage-note">Klik “Edit bagian ini” pada halaman. Perubahan tampil ke publik setelah diterbitkan.</p>
        </section>
        <form class="cms-inspector" method="post" action="/admin/website/{{ $section }}/edit" enctype="multipart/form-data" data-cms-form data-preview-url="/admin/website/{{ $section }}/preview?page={{ $previewPage }}">
            @csrf<input type="hidden" name="version" value="{{ $version }}">
            <div class="cms-inspector-scroll">
                @foreach($blocks as $id=>$block)
                <section data-cms-panel="{{ $id }}" @if($id!==$selected) hidden @endif>
                    <header class="cms-panel-heading"><span class="cms-eyebrow">EDIT BAGIAN</span><h3>{{ $block['label'] }}</h3></header>
                    @if($section==='teachers' && $id==='people')
                        @include('website.editor-people')
                    @else
                        @foreach($block['keys'] as $path)
                            @include('website.editor-node', ['node'=>\Illuminate\Support\Arr::get($data,$path), 'path'=>$path])
                        @endforeach
                    @endif
                    @if($section==='teachers' && $id==='team')<p class="cms-help">Kartu ringkasan pada beranda versi tablet. Direktori lengkap dikelola pada bagian Guru & Staf.</p>@endif
                    @if($section==='teachers' && $id==='structure')<p class="cms-help">Pilih anggota berdasarkan nama. Nama dan foto mengikuti data direktori.</p>@endif
                </section>
                @endforeach
            </div>
            <footer class="cms-savebar"><span data-cms-status role="status">Draf siap diedit</span><button class="btn secondary" type="button" data-cms-preview>Perbarui pratinjau</button><button class="btn" type="submit">Simpan draf</button></footer>
        </form>
    </div>
</div>
<dialog class="cms-confirm" data-cms-confirm><h2>Hapus item dari draf?</h2><p>Perubahan ini diterapkan pada draf. Halaman publik diperbarui saat draf diterbitkan.</p><div class="actions"><button class="btn secondary" type="button" data-cms-cancel>Batal</button><button class="btn danger" type="button" data-cms-accept>Hapus dari draf</button></div></dialog>
@endsection
