@props(['title'=>null])
<section {{ $attributes->merge(['class'=>'card']) }}>@if($title)<div class="card-heading"><h3>{{ __($title) }}</h3><span class="more" aria-hidden="true">•••</span></div>@endif{{ $slot }}</section>
