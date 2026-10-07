@props(['items'])
<div class="stats" style="--cols:{{ count($items) }}">@foreach($items as $item)<div class="stat"><span class="stat-icon tone-{{ $loop->index }}"><x-icon :name="$item[2] ?? 'file'"/></span><div><strong>{{ $item[0] }}</strong><span>{{ __($item[1]) }}</span></div></div>@endforeach</div>
