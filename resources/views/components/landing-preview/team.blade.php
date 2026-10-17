@props(['site'])
<div class="card-grid team-grid">@foreach($site['teachers']['team'] as $member)<article class="team-card"><img src="{{ $member['photo'] }}" alt="{{ $member['name'] }}" width="512" height="250" loading="lazy"><h3>{{ $member['name'] }}</h3><p>{{ $member['role'] }}</p></article>@endforeach</div>
