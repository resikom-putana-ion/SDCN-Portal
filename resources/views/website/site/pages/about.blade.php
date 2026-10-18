@extends('website.site.layout')
@section('title','Tentang Kami — SD Ceria Nusantara')
@section('content')
<x-landing-preview.hero :data="$site['about']"/>
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="CERITA KAMI" :title="$site['about']['story_title']" :subtitle="$site['about']['story_subtitle']"/><div class="story-grid"><img src="{{ $site['settings']['classroom_image'] }}" alt="Ruang kelas SD Ceria Nusantara" width="1672" height="941"><div class="story-copy"><p class="eyebrow">BELAJAR BERSAMA</p><p>{{ $site['about']['story'] }}</p><small>Berdiri sejak {{ $site['settings']['founded'] }} • Pembelajaran aktif • Kemitraan keluarga</small></div></div></div></section>
<section class="section tinted"><div class="container"><x-landing-preview.heading eyebrow="ARAH PENDIDIKAN" title="Nilai yang kami bawa setiap hari"/><x-landing-preview.cards :items="$site['about']['values']"/></div></section>
@php($principal = \App\Services\TeacherDirectory::principal($site['teachers']))
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="SAMBUTAN" title="Suara dari pemimpin sekolah" :subtitle="'Sambutan '.$principal['name']"/><blockquote class="quote-card"><p>{{ $site['about']['quote'] }}</p><cite>— {{ $principal['name'] }} • Kepala Sekolah</cite></blockquote></div></section>
@endsection
