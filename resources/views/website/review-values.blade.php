<div @if($prefix === '') class="grid-3" @endif style="margin-top:20px">
@foreach($items as $key=>$value)
@php($path = $prefix.$key)
@if($key !== 'icon' && $value !== null && $value !== '')
<div style="min-width:0;overflow-wrap:anywhere">
<h4>{{ is_numeric($key) ? 'Kartu '.($key+1) : \App\Services\ContentLabels::label((string)$key) }}</h4>
@if(is_array($value))
@include('website.review-values', ['items'=>$value, 'prefix'=>$path.'.'])
@elseif(preg_match('/(^image$|_image$|^logo$|\.photo$)/', $path))
<img src="{{ \App\Services\WebsiteContent::imageUrl((string)$value) }}" alt="{{ \App\Services\ContentLabels::label($path) }}" style="max-width:100%;max-height:220px;object-fit:cover">
@else<p style="white-space:pre-line">{{ is_bool($value) ? ($value ? 'Ya' : 'Tidak') : $value }}</p>@endif
</div>
@endif
@endforeach
</div>
