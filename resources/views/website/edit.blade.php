@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/css/website-editor.css?v=20261018">
<script src="/js/website-editor.js?v=20261018" defer></script>
@php
    $selected = old('block', request('block'));
    if (!is_string($selected) || !isset($blocks[$selected])) $selected = array_key_first($blocks);
    $previewPage = $section === 'settings' ? 'home' : $section;
    if ($section === 'settings' && $selected === 'legal') $previewPage = 'privacy';
    if ($section === 'teachers' && $selected === 'team') $previewPage = 'home';
@endphp
<div class="cms cms-editor" data-website-editor data-section="{{ $section }}" data-initial-block="{{ $selected }}" data-preview-page="{{ $previewPage }}">
    <div class="cms-topline">
        <div><a class="cms-back" href="/admin/website">← Semua halaman</a><h2>{{ \App\Services\WebsiteContent::SECTIONS[$section] }}</h2><p>Ubah isi, simpan draf, lalu tinjau perubahan sebelum diterbitkan.</p></div>
        <div class="actions"><button class="btn secondary" type="button" data-cms-toggle-preview aria-expanded="true">Sembunyikan pratinjau</button><button class="btn" type="submit" form="website-editor-form" name="intent" value="review">Simpan & tinjau</button></div>
    </div>
    <nav class="cms-pages" aria-label="Halaman website">
        @foreach(\App\Services\LandingPreview::PAGES + ['settings'=>'Informasi sekolah'] as $key=>$label)
        <a href="/admin/website/{{ $key }}/edit" @if($key===$section) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="cms-editor-status"><span class="cms-state" data-cms-draft-badge>{{ $draft ? 'Draf belum diterbitkan' : 'Sesuai versi terbit' }}</span><span data-cms-status role="status">{{ $draft ? 'Draf tersimpan. Anda dapat melanjutkan mengedit.' : 'Belum ada perubahan pada halaman ini.' }}</span></div>
    <div class="cms-workspace">
        <aside class="cms-outline">
            <nav aria-label="Bagian halaman">@foreach($blocks as $id=>$block)<a href="?block={{ $id }}" data-cms-select="{{ $id }}" @if($id===$selected) aria-current="true" @endif><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span>{{ $block['label'] }}</a>@endforeach</nav>
        </aside>
        <section class="cms-stage" aria-label="Pratinjau interaktif">
            <div class="cms-stage-toolbar"><span><i></i> Pratinjau draf</span><div class="cms-device" role="group" aria-label="Ukuran pratinjau"><button type="button" data-cms-width="1280" aria-pressed="true">Desktop</button><button type="button" data-cms-width="768" aria-pressed="false">Tablet</button><button type="button" data-cms-width="390" aria-pressed="false">Ponsel</button></div></div>
            <div class="cms-frame-wrap" data-cms-frame-wrap><iframe title="Pratinjau landing page yang dapat diedit" data-cms-frame src="/admin/website/preview/frame?page={{ $previewPage }}&edit=1" sandbox="allow-scripts allow-same-origin"></iframe></div>
            <p class="cms-stage-note" data-cms-preview-status>Pratinjau mengikuti isian Anda. Klik bagian di halaman untuk mengeditnya.</p>
        </section>
        <form id="website-editor-form" class="cms-inspector" method="post" action="/admin/website/{{ $section }}/edit" enctype="multipart/form-data" data-cms-form data-preview-url="/admin/website/{{ $section }}/preview?page={{ $previewPage }}">
            @csrf<input type="hidden" name="version" value="{{ old('version', $version) }}"><input type="hidden" name="block" value="{{ $selected }}">
            <div class="cms-form-errors" data-cms-errors role="alert" hidden></div>
            <fieldset class="cms-inspector-scroll">
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
            </fieldset>
            <footer class="cms-savebar"><span>Perubahan tampil ke publik setelah diterbitkan.</span><button class="btn secondary" type="button" data-cms-preview>Perbarui pratinjau</button><button class="btn" type="submit" name="intent" value="save">Simpan draf</button></footer>
        </form>
    </div>
</div>
<dialog class="cms-confirm" data-cms-confirm><h2>Hapus item dari draf?</h2><p>Perubahan ini diterapkan pada draf. Halaman publik diperbarui saat draf diterbitkan.</p><div class="actions"><button class="btn secondary" type="button" data-cms-cancel>Batal</button><button class="btn danger" type="button" data-cms-accept>Hapus dari draf</button></div></dialog>
@endsection
