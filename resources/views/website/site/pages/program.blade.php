@extends('website.site.layout')
@section('title','Program — SD Ceria Nusantara')
@section('content')
<x-landing-preview.hero :data="$site['program']"/>
<section class="section" id="pendekatan"><div class="container"><x-landing-preview.heading eyebrow="PENDEKATAN" title="Belajar aktif, tumbuh utuh" subtitle="Menyeimbangkan akademik, karakter, dan kreativitas anak."/><x-landing-preview.cards :items="$site['program']['approach']"/></div></section>
<section class="section tinted"><div class="container"><x-landing-preview.heading eyebrow="KURIKULUM" title="Terhubung dengan kehidupan anak" subtitle="Kurikulum terpadu dengan proyek, eksplorasi, dan refleksi."/><div class="card-grid">@foreach($site['program']['curriculum'] as $item)<article class="info-card"><h3>{{ $item['title'] }}</h3><ul>@foreach(explode("\n",$item['text']) as $line)<li>{{ $line }}</li>@endforeach</ul></article>@endforeach</div></div></section>
<section class="section"><div class="container"><x-landing-preview.heading eyebrow="KEGIATAN" title="Jelajahi minat di luar kelas" subtitle="Kegiatan setelah kelas untuk menumbuhkan minat dan bakat."/><x-landing-preview.cards :items="$site['program']['activities']"/></div></section>
@endsection
