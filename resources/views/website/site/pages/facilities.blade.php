@extends('website.site.layout')
@section('title','Fasilitas — SD Ceria Nusantara')
@section('content')
<x-landing-preview.hero :data="$site['facilities']"/>
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="JELAJAHI RUANG" title="Fasilitas untuk berbagai cara belajar" subtitle="Ruang yang mendukung rasa ingin tahu dan kreativitas anak."/><x-landing-preview.facilities :site="$site"/></div></section>
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="PENGALAMAN ANAK" title="Nyaman untuk eksplorasi sehari-hari"/><x-landing-preview.cards :items="$site['facilities']['features']"/><p class="image-caption">Kunjungi sekolah dan temukan ruang belajar favorit anak.</p></div></section>
@endsection
