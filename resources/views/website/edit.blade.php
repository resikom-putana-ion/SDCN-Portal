@extends('layouts.app')
@section('content')
@include('website.toolbar')
<div class="tabs">@foreach(\App\Services\WebsiteContent::SECTIONS as $key=>$label)<a class="{{ $section===$key?'active':'' }}" href="/admin/website/{{ $key }}/edit">{{ $label }}</a>@endforeach</div>
@if($section === 'teachers')
<x-notice>Ringkasan beranda, direktori Guru & Staf, mata pelajaran, dan struktur organisasi dikelola terpisah di bawah. Data kepegawaian pada menu Guru tidak otomatis diterbitkan ke website. Buka kartu untuk mengedit; susunan anggota tetap dipertahankan.</x-notice>
@endif
<p class="muted">Simpan sebagai draf, periksa isinya, lalu terbitkan. <a href="{{ rtrim(config('school.landing_url'), '/') }}/{{ ['home'=>'','about'=>'tentang-kami','teachers'=>'guru-staf','program'=>'program','facilities'=>'fasilitas','gallery'=>'galeri','contact'=>'kontak','settings'=>''][$section] }}" target="_blank" rel="noopener">Lihat halaman yang sudah terbit ↗</a></p>
@php
    $groups = [];
    foreach ($fields as $key => $value) {
        if (is_array($value) || str_ends_with($key, '.icon') || str_ends_with($key, '.portrait')) continue;
        if ($section === 'teachers' && in_array($key, ['eyebrow', 'title', 'description', 'image'])) continue;
        $parts = explode('.', $key);
        $group = count($parts) > 1 ? $parts[0].(isset($parts[1]) && ctype_digit($parts[1]) ? '.'.$parts[1] : '') : 'general';
        $groups[$group][$key] = $value;
    }
    $groupTitles = ['general'=>'Informasi utama', 'team'=>'Ringkasan guru di beranda', 'people'=>'Direktori Guru & Staf', 'subjects'=>'Mata pelajaran', 'structure'=>'Struktur organisasi'];
@endphp
<form method="post" action="/admin/website/{{ $section }}/edit" enctype="multipart/form-data">@csrf
<input type="hidden" name="version" value="{{ $version }}">
@foreach($groups as $group => $items)
@php
    $parts = explode('.', $group);
    $heading = ($groupTitles[$parts[0]] ?? \App\Services\ContentLabels::label($parts[0]));
    $item = \Illuminate\Support\Arr::get($data, $group, []);
    if (isset($parts[1])) $heading .= ' '.((int)$parts[1]+1).' — '.($item['name'] ?? $item['title'] ?? $item['question'] ?? 'Isi kartu');
@endphp
<details class="card" @if($loop->first) open @endif>
<summary style="cursor:pointer;font-weight:600;padding:8px 0">{{ $heading }}</summary>
@if($parts[0] === 'people')
<div style="margin:16px 0;width:100px;height:120px;border-radius:12px;{{ !empty($item['photo']) ? 'background:url('.\App\Services\WebsiteContent::imageUrl($item['photo']).') center / cover' : 'background-image:url('.rtrim(config('school.landing_url'),'/').'/assets/team/team-portraits.png);background-size:500% 600%;background-position:'.(($item['portrait']%5)*25).'% '.(intdiv($item['portrait'],5)*20).'%' }}" role="img" aria-label="Foto {{ $item['name'] }}"></div>
@endif
<div class="form-grid" style="margin-top:16px">
@foreach($items as $key=>$value)
@include('website.field', ['fieldIndex' => array_search($key, array_keys($fields))])
@endforeach
</div>
</details>
@endforeach
<div class="actions"><button class="btn">Simpan draf</button><a href="/admin/website/preview" class="btn secondary">Tinjau isi draf</a><a href="/admin/halaman" class="btn white">Kembali</a></div>
</form>
@endsection
