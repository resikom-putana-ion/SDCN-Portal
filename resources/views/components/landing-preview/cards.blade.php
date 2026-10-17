@props(['items'])
<div {{ $attributes->class(['card-grid']) }}>@foreach($items as $item)<article class="info-card"><x-landing-preview.icon :name="$item['icon'] ?? 'book'"/><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></article>@endforeach</div>
