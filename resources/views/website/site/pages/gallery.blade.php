@extends('website.site.layout')
@section('title','Berita & Galeri — SD Ceria Nusantara')
@section('content')
<x-landing-preview.hero :data="$site['gallery']"/>
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="CERITA SEKOLAH" title="Yang sedang berlangsung" subtitle="Kegiatan, karya, dan cerita terbaru dari keluarga sekolah."/><div class="card-grid">@foreach($site['gallery']['news'] as $item)<article class="program-card news-card"><div class="program-cover color-{{ $loop->index % 3 }}"><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span></div><details class="news-content"><summary><p class="eyebrow">{{ $item['category'] }}</p><h3>{{ $item['title'] }}</h3><p>{{ $item['date'] }} • Cerita Sekolah</p></summary><p>{{ $item['text'] }}</p></details></article>@endforeach</div></div></section>
<section class="section tinted"><div class="container"><x-landing-preview.heading eyebrow="GALERI" title="Momen yang ingin dikenang" subtitle="Merekam kebersamaan, penemuan kecil, dan karya anak."/><x-landing-preview.facilities :site="$site" :labels="false"/></div></section>
@endsection
