@extends('website.site.layout')
@section('title', $site['teachers']['directory_title'].' — SD Ceria Nusantara')
@section('content')
@php
    $directory = $site['teachers'];
    $people = $directory['people'];
    $structure = $directory['structure'];
    $head = $people[$structure['head']];
    $groupCounts = array_count_values(array_column($people, 'group'));
    $gradeCount = count(array_filter($people, fn ($person) => str_starts_with(mb_strtolower($person['role']), 'wali kelas')));
@endphp
<section class="people-hero outer">
    <div><p class="eyebrow">{{ $directory['directory_eyebrow'] }}</p><h1 style="white-space:pre-line">{{ $directory['directory_title'] }}</h1><p>{{ $directory['directory_description'] }}</p><div class="people-hero-actions"><a class="button" href="#direktori">Berkenalan dengan tim ↗</a><a class="text-link" href="#struktur">Lihat struktur sekolah ↓</a></div><div class="people-facts"><span><strong>{{ $gradeCount }}</strong> jenjang kelas</span><span><strong>{{ count($people) }}</strong> {{ $directory['demo_profiles'] ? 'profil contoh' : 'anggota tim' }}</span><span><strong>1</strong> semangat mendampingi</span></div></div>
    <div class="people-hero-photo"><x-landing-preview.staff-portrait :index="$head['portrait']" :name="$head['name']" :photo="$head['photo']"/><div class="people-photo-note"><span aria-hidden="true">♡</span><div><strong>Setiap anak dikenal,</strong><br>setiap keluarga didengar.</div></div><span class="people-photo-badge">Bertumbuh bersama</span></div>
</section>
@if($directory['demo_profiles'])<div class="container people-demo-note"><span aria-hidden="true">ⓘ</span><p><strong>Contoh profil sekolah.</strong> Nama, foto, jabatan, dan pembagian tugas pada halaman ini ditandai sebagai data contoh.</p></div>@endif
<section class="section people-values"><div class="container"><x-landing-preview.heading eyebrow="LEBIH DARI MENGAJAR" title="Mendampingi dengan hati" subtitle="Kami ingin anak merasa aman, orang tua merasa tenang, dan komunikasi selalu terbuka."/><x-landing-preview.cards :items="$directory['values']"/></div></section>
<section class="section tinted" id="direktori"><div class="container">
    <x-landing-preview.heading :eyebrow="$directory['eyebrow']" :title="$directory['title']" :subtitle="$directory['description']"/>
    <div class="people-tools"><div class="people-filters" role="group" aria-label="Filter tim sekolah"><button type="button" class="is-active" data-team-filter="semua" aria-pressed="true">Semua tim <span>{{ count($people) }}</span></button><button type="button" data-team-filter="pimpinan" aria-pressed="false">Pimpinan <span>{{ $groupCounts['pimpinan'] ?? 0 }}</span></button><button type="button" data-team-filter="guru" aria-pressed="false">Guru <span>{{ $groupCounts['guru'] ?? 0 }}</span></button><button type="button" data-team-filter="staf" aria-pressed="false">Staf pendukung <span>{{ $groupCounts['staf'] ?? 0 }}</span></button></div><label class="people-search"><span>Cari nama atau peran</span><input type="search" data-team-search placeholder="Misalnya: wali kelas, UKS…" autocomplete="off"></label></div>
    <p class="people-results" data-team-count aria-live="polite">Menampilkan {{ count($people) }} anggota tim</p>
    <div class="people-grid">
    @foreach($people as $person)
        <article class="person-card" data-team-group="{{ $person['group'] }}" data-team-text="{{ mb_strtolower($person['name'].' '.$person['role'].' '.$person['description']) }}"><div class="person-photo"><x-landing-preview.staff-portrait :index="$person['portrait']" :name="$person['name']" :photo="$person['photo']"/><span class="person-category">{{ ['pimpinan'=>'Pimpinan sekolah','guru'=>'Tim pengajar','staf'=>'Sahabat sekolah'][$person['group']] }}</span></div><div class="person-copy"><p class="person-role">{{ $person['role'] }}</p><h3>{{ $person['name'] }}</h3><p>{{ $person['description'] }}</p></div></article>
    @endforeach
    </div><p class="people-empty" data-team-empty hidden>Belum ada profil yang cocok. Coba nama atau peran lain.</p>
</div></section>
<section class="section people-subjects" id="mata-pelajaran"><div class="container"><x-landing-preview.heading eyebrow="BELAJAR UTUH, BERTUMBUH BERSAMA" title="Siapa yang mendampingi pelajaran anak?" subtitle="Wali kelas mendampingi pelajaran inti, bekerja sama dengan guru bidang studi."/><div class="subject-grid">
@foreach($directory['subjects'] as $subject)

<article class="subject-card"><span class="subject-number" aria-hidden="true">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h3>{{ $subject['title'] }}</h3><p>{{ $subject['text'] }}</p><div>{{ $subject['by'] ?? '' }}</div></article>
@endforeach
</div><p class="people-subject-note">Pembagian pelajaran menyesuaikan jenjang, kurikulum, dan program sekolah. Muatan lokal, pilihan seni, serta kegiatan pengayaan dapat berbeda pada setiap kelas.</p></div></section>
<section class="section tinted people-organization" id="struktur"><div class="container"><x-landing-preview.heading eyebrow="SALING TERHUBUNG, SALING MENDUKUNG" title="Struktur keluarga sekolah" subtitle="Seluruh guru dan staf ditampilkan per orang agar peran setiap anggota tim mudah dikenali."/>
<div class="organization-interaction-note"><span aria-hidden="true">↑</span><p><strong>Kenali wajah di setiap peran.</strong> Arahkan kursor, fokuskan dengan keyboard, atau sentuh kartu untuk memunculkan foto dari bawah.</p><b>{{ count($people) }} orang</b></div>
<div class="organization-tree" aria-label="Struktur kepegawaian sekolah lengkap">
    <div class="organization-head-wrap">
        <span class="organization-level-label">Pimpinan Sekolah</span>
        <x-landing-preview.organization-person :person="$people[$structure['head']]" variant="head" label="Kepala Sekolah"/>
    </div>
    <div class="organization-branches">
        @foreach($structure['branches'] as $branch)
        @php($branchMemberCount = 1 + array_sum(array_map(static fn ($team) => count($team['members']), $branch['teams'])))
        <section class="organization-branch is-{{ $branch['tone'] }}" aria-labelledby="organization-branch-{{ $loop->iteration }}">
            <header class="organization-branch-heading">
                <span class="organization-branch-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div><p>BIDANG KOORDINASI</p><h3 id="organization-branch-{{ $loop->iteration }}">{{ $branch['title'] }}</h3><span>{{ $branch['summary'] }}</span></div>
                <b>{{ $branchMemberCount }} orang</b>
            </header>
            <div class="organization-branch-body">
                <div class="organization-lead-column">
                    <p class="organization-column-label">Koordinator bidang</p>
                    <x-landing-preview.organization-person :person="$people[$branch['lead']]" variant="lead" label="Pimpinan bidang"/>
                </div>
                <div class="organization-teams">
                    @foreach($branch['teams'] as $team)
                    <section class="organization-team">
                        <header><h4>{{ $team['title'] }}</h4><span>{{ count($team['members']) }} orang</span></header>
                        <div class="organization-people-grid">
                            @foreach($team['members'] as $personIndex)
                            <x-landing-preview.organization-person :person="$people[$personIndex]"/>
                            @endforeach
                        </div>
                    </section>
                    @endforeach
                </div>
            </div>
        </section>
        @endforeach
    </div>
</div><p class="people-subject-note">Bagan ditampilkan mengikuti susunan organisasi yang dikelola sekolah.</p>
</div></section>
<section class="section"><div class="container people-help"><div><p class="eyebrow">KAMI SIAP MENDENGARKAN</p><h2>Butuh berbicara dengan tim yang tepat?</h2><p>Mulai dari pertanyaan belajar anak, administrasi, hingga rencana kunjungan. Tim tata usaha akan membantu menghubungkan Anda.</p></div><a class="button" href="{{ \App\Services\LandingPreview::url('contact') }}">Hubungi sekolah ↗</a></div></section>
@endsection
@push('scripts')
<script src="/landing-preview/js/team-directory.js" defer></script>
@endpush
