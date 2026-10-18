@props(['site'])
<div class="card-grid team-grid">@foreach(\App\Services\TeacherDirectory::homepagePeople($site['teachers']) as $member)<article class="team-card"><x-landing-preview.person-photo :person="$member"/><h3>{{ $member['name'] }}</h3><p>{{ $member['role'] }}</p></article>@endforeach</div>
