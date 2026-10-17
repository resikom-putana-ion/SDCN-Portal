<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $site['home']['description'] }}">
    <meta name="theme-color" content="#095d89">
    <title>@yield('title', $site['settings']['school_name'])</title>
    <link rel="icon" href="{{ $site['settings']['logo'] }}" type="image/png">
    <link rel="preload" href="/assets/Poppins-Regular.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="/landing-preview/css/site.css">
    <link rel="stylesheet" href="/landing-preview/css/hero-motion.css">
    <link rel="stylesheet" href="/landing-preview/css/announcement.css">
    @if(($page ?? '') === 'teachers')<link rel="stylesheet" href="/landing-preview/css/team-directory.css">@endif
    @if(($page ?? '') === 'registration')<link rel="stylesheet" href="/landing-preview/css/registration.css">@endif
    <script src="/landing-preview/js/site.js" defer></script>
</head>
<body class="page-{{ $page ?? 'registration' }}">
<a class="skip-link" href="#main">Lewati ke konten</a>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="{{ \App\Services\LandingPreview::url('home') }}" aria-label="SD Ceria Nusantara, beranda">
            <span class="brand-emblem" aria-hidden="true">
                <img src="{{ $site['settings']['logo'] }}" alt="" width="62" height="62">
            </span>
            <span>SD CERIA<small>NUSANTARA</small></span>
        </a>
        <nav id="navigation" aria-label="Navigasi utama">
            @foreach(['home'=>'Beranda','about'=>'Tentang Kami','program'=>'Program','facilities'=>'Fasilitas','teachers'=>'Guru & Staf','gallery'=>'Galeri','contact'=>'Kontak','announcement'=>'Pengumuman'] as $key=>$label)
            <a href="{{ \App\Services\LandingPreview::url($key) }}" @if(($page ?? '') === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <a class="button small" href="{{ \App\Services\LandingPreview::url('registration', 1) }}">Daftar Sekarang</a>
        <button class="menu-toggle" aria-controls="navigation" aria-expanded="false" aria-label="Buka menu"><span></span><span></span><span></span></button>
    </div>
</header>
<main id="main">
    @yield('content')
    @unless(isset($hideCta))
    <section class="cta outer {{ ($page ?? '') === 'home' ? 'home-cta' : '' }}">
        <div>@if(($page ?? '') === 'home')<p class="eyebrow">SIAP BERKENALAN DENGAN KAMI?</p>@endif
        <h2>{{ ($page ?? '') === 'home' ? 'Mari mulai langkah pertama bersama.' : 'Siap mengenal SD Ceria Nusantara lebih dekat?' }}</h2>
        <p>{{ ($page ?? '') === 'home' ? 'Daftar secara online atau jadwalkan kunjungan ke sekolah.' : 'Hubungi kami atau mulai proses pendaftaran online.' }}</p></div>
        <div class="cta-actions"><a class="button" href="{{ \App\Services\LandingPreview::url('registration', 1) }}">Daftar Sekarang</a>
        @if(($page ?? '') === 'home')<a class="button outline" href="{{ \App\Services\LandingPreview::url('contact') }}#kunjungan">Jadwalkan Kunjungan</a>@endif</div>
    </section>
    @endunless
</main>
<footer class="site-footer"><div class="container footer-inner">
    <div class="footer-brand"><img src="{{ $site['settings']['logo'] }}" width="58" height="58" alt=""><div><strong>SD CERIA NUSANTARA</strong><p>{{ $site['settings']['tagline'] }}</p></div></div>
    <div class="footer-contact"><p><a href="mailto:{{ $site['settings']['email'] }}">{{ $site['settings']['email'] }}</a> • <a href="https://wa.me/{{ preg_replace('/\D/', '', $site['settings']['whatsapp']) }}" target="_blank" rel="noopener">WA {{ $site['settings']['whatsapp'] }}</a></p><p>{{ $site['settings']['address'] }}</p><p>{{ $site['settings']['hours'] }}</p></div>
    @if(($page ?? '') === 'home')<div class="footer-bottom"><span>© {{ date('Y') }} SD Ceria Nusantara. Hak cipta dilindungi.</span><span><a href="{{ \App\Services\LandingPreview::url('privacy') }}">Privasi</a> • <a href="{{ \App\Services\LandingPreview::url('terms') }}">Syarat & Ketentuan</a></span></div>@endif
</div></footer>
<a class="whatsapp-float" data-whatsapp-float href="https://wa.me/{{ preg_replace('/\D/', '', $site['settings']['whatsapp']) }}" target="_blank" rel="noopener" aria-label="Tanya via WhatsApp" title="Tanya via WhatsApp">
    <img src="/landing-preview/assets/design/whatsapp.svg" alt="" width="38" height="38">
    <span>Tanya via WhatsApp</span>
</a>
<dialog id="image-dialog" class="image-dialog"><button class="dialog-close" type="button" aria-label="Tutup gambar">×</button><img alt=""><p></p></dialog>
@stack('scripts')
<script type="application/json" id="preview-blocks">{!! json_encode($previewBlocks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="/js/website-preview.js" defer></script>
<link rel="stylesheet" href="/css/website-preview.css">
</body></html>
