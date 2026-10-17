<article style="padding:16px;border:1px solid var(--border);border-radius:12px">
@if(!empty($person['photo']))
<img src="{{ \App\Services\WebsiteContent::imageUrl($person['photo']) }}" alt="{{ $person['name'] }}" style="width:110px;height:130px;object-fit:cover;border-radius:12px">
@elseif(isset($person['portrait']))
<div role="img" aria-label="Foto {{ $person['name'] }}" style="width:110px;height:130px;border-radius:12px;background-image:url('{{ rtrim(config('school.landing_url'),'/') }}/assets/team/team-portraits.png');background-size:500% 600%;background-position:{{ ($person['portrait']%5)*25 }}% {{ intdiv($person['portrait'],5)*20 }}%"></div>
@endif
<h4>{{ $person['name'] }}</h4><p>{{ $person['role'] }}</p><p>{{ $person['description'] ?? '' }}</p>
</article>
