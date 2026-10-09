@php($settings=$content['settings']??[])
<div class="public-nav"><a href="/website" class="brand"><strong>{{ $settings['school_name']??'SD Ceria Nusantara' }}</strong></a><a href="#about">Profil</a><a href="#program">Program</a><a href="#facilities">Fasilitas</a><a class="btn" href="/ppdb">Daftar Sekarang</a></div>
@foreach($content as $section=>$data)
<section class="public-section" id="{{ $section }}">
@if($section==='home')<div class="public-hero"><div><span class="eyebrow">{{ $data['eyebrow']??'' }}</span><h1>{{ $data['title']??'' }}</h1><p>{{ $data['description']??'' }}</p></div>@if(!empty($data['image']))<img src="{{ \App\Services\WebsiteContent::image($data['image']) }}" alt="{{ $data['title']??'' }}">@endif</div>
@else<h2>{{ \App\Services\WebsiteContent::SECTIONS[$section]??$section }}</h2>@endif
<div class="grid-3">@foreach(\Illuminate\Support\Arr::dot($data) as $key=>$value)
@if(is_scalar($value) && $value!=='' && !str_ends_with($key,'.icon'))
@if(preg_match('/(^image$|_image$|^logo$|\.photo$)/',$key))<img src="{{ \App\Services\WebsiteContent::image((string)$value) }}" alt="{{ \App\Services\ContentLabels::label($key) }}" style="max-width:100%;max-height:220px;object-fit:cover">
@elseif(!($section==='home' && in_array($key,['eyebrow','title','description'])))<div><h3>{{ \App\Services\ContentLabels::label($key) }}</h3><p style="white-space:pre-line">{{ $value }}</p></div>@endif
@endif
@endforeach</div></section>
@endforeach
<footer class="public-footer"><strong>{{ $settings['school_name']??'' }}</strong><p>{{ $settings['email']??'' }} ? {{ $settings['whatsapp']??'' }}</p><p>{{ $settings['address']??'' }}</p></footer>
