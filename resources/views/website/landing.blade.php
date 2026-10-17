<x-notice>Ini tinjauan isi draf sebelum diterbitkan. Susunan visual website dapat dilihat pada <a href="{{ config('school.landing_url') }}" target="_blank" rel="noopener">landing page yang sudah terbit ↗</a>.</x-notice>
@foreach($content as $section=>$data)
<x-card :title="\App\Services\WebsiteContent::SECTIONS[$section] ?? $section">
<div class="actions"><a href="/admin/website/{{ $section }}/edit">Edit bagian ini</a></div>
@if($section === 'teachers')
    <h3>Ringkasan guru di beranda</h3>
    <div class="grid-3">@foreach($data['team'] ?? [] as $person) @include('website.person', ['person'=>$person]) @endforeach</div>
    <h3 style="margin-top:24px">{{ $data['directory_title'] }}</h3><p>{{ $data['directory_description'] }}</p>
    @if($data['demo_profiles'])<p class="muted">Profil masih ditandai sebagai contoh.</p>@endif
    <div class="grid-3">@foreach($data['people'] as $person) @include('website.person', ['person'=>$person]) @endforeach</div>
    <h3 style="margin-top:24px">Mata pelajaran</h3>
    <div class="grid-3">@foreach($data['subjects'] as $subject)<article><h4>{{ $subject['title'] }}</h4><p>{{ $subject['by'] }}</p><p>{{ $subject['text'] }}</p></article>@endforeach</div>
    <h3 style="margin-top:24px">Struktur organisasi</h3>
    <p>Kepala sekolah: {{ $data['people'][$data['structure']['head']]['name'] ?? 'Anggota tidak tersedia' }}</p>
    <div class="grid-3">@foreach($data['structure']['branches'] as $branch)<article><h4>{{ $branch['title'] }}</h4><p>{{ $branch['summary'] }}</p><p>Koordinator: {{ $data['people'][$branch['lead']]['name'] ?? 'Anggota tidak tersedia' }}</p>
    @foreach($branch['teams'] as $team)<strong>{{ $team['title'] }}</strong><ul>@foreach($team['members'] as $index)<li>{{ $data['people'][$index]['name'] ?? 'Anggota tidak tersedia' }}</li>@endforeach</ul>@endforeach</article>@endforeach</div>
    @include('website.review-values', ['items'=>['values'=>$data['values'] ?? []], 'prefix'=>''])
@else
    @include('website.review-values', ['items'=>$data, 'prefix'=>''])
@endif
</x-card>
@endforeach
