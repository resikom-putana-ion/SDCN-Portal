@props(['data','home'=>false,'contact'=>false])
<section class="hero outer {{ $home ? 'hero-home' : '' }} {{ $contact ? 'hero-contact' : '' }}">
    <div class="hero-decoration" aria-hidden="true"></div>
    <div class="hero-playground" aria-hidden="true">
        <div class="hero-glow"></div>
        <svg class="hero-sun" viewBox="0 0 100 100" fill="none"><g stroke="#ffcf32" stroke-width="5" stroke-linecap="round"><path d="M50 5v9m0 72v9M5 50h9m72 0h9M18 18l7 7m50 50 7 7M18 82l7-7m50-50 7-7"/></g><circle cx="50" cy="50" r="27" fill="#ffcf32"/><path d="M39 45v3m22-3v3m-21 9q10 12 20 0" stroke="#0a3556" stroke-width="3.5" stroke-linecap="round"/><circle cx="33" cy="55" r="4" fill="#ffa66b"/><circle cx="67" cy="55" r="4" fill="#ffa66b"/></svg>
        <svg class="hero-star" viewBox="0 0 60 60"><path d="m30 3 8 17 19 3-14 14 3 20-16-9-17 9 4-20L3 23l19-3Z" fill="#ffe797"/><path d="M24 30h1m10 0h1m-11 7q5 5 10 0" fill="none" stroke="#175375" stroke-width="3" stroke-linecap="round"/></svg>
        <svg class="hero-cloud" viewBox="0 0 150 65"><path d="M25 60a22 22 0 0 1-2-44A30 30 0 0 1 78 9a24 24 0 0 1 40 13 19 19 0 1 1 5 38Z" fill="#bdeeff"/></svg>
    </div>
    <button class="hero-motion-toggle" type="button" data-hero-motion aria-pressed="false" aria-label="Jeda animasi dekorasi">Ⅱ <span>Jeda animasi</span></button>
    <div class="hero-copy"><p class="hero-eyebrow">{{ $data['eyebrow'] }}</p><h1>{{ $data['title'] }}</h1><p class="hero-description">@if(isset($data['tablet_description']))<span class="desktop-only">{{ $data['description'] }}</span><span class="tablet-only">{{ $data['tablet_description'] }}</span>@else{{ $data['description'] }}@endif</p>
    <div class="hero-actions"><a class="button" href="{{ \App\Services\LandingPreview::url('registration',1) }}">Daftar Sekarang</a>@if($home)<a class="button white" href="{{ \App\Services\LandingPreview::url('about') }}">Lihat Profil Sekolah</a>@endif</div></div>
    <figure class="hero-image {{ $home ? 'hero-slider' : '' }}" @if($home) data-hero-slider @endif>
        @if($home)
        <div class="hero-slides">
            @foreach([
                ['src'=>$data['campus_image'] ?? '/landing-preview/assets/design/sd-ceria-nusantara-campus.png','alt'=>'Kawasan SD Ceria Nusantara dari udara','caption'=>'Kawasan SD Ceria Nusantara'],
                ['src'=>$data['image'],'alt'=>'Siswa belajar bersama di kelas','caption'=>'Belajar bersama','source'=>null],
                ['src'=>'/landing-preview/assets/design/asset-04.png','alt'=>'Suasana ruang kelas SD Ceria Nusantara','caption'=>'Ruang belajar yang ceria','source'=>null],
            ] as $slide)
            <img class="hero-slide @if($loop->first) is-active @endif" src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" data-caption="{{ $slide['caption'] }}" data-concept="{{ $slide['concept'] ?? '' }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif width="1672" height="1045" @if(!$loop->first) aria-hidden="true" @endif>
            @endforeach
        </div>
        @else
        <img src="{{ $data['image'] }}" alt="{{ $contact ? 'Logo SD Ceria Nusantara' : 'Suasana belajar di SD Ceria Nusantara' }}" fetchpriority="high" width="1672" height="941">
        @endif
        @unless($contact)
            @if($home)
            <figcaption><span data-hero-caption>Kawasan SD Ceria Nusantara</span></figcaption>
            @else
            <figcaption>Belajar bersama</figcaption>
            @endif
        @endunless
    </figure>
</section>
