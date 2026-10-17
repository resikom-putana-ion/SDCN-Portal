<!DOCTYPE html><html lang="{{ session('locale','id') }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __($title ?? 'Beranda') }} · SD Ceria Nusantara</title><link rel="icon" href="/assets/logo.png"><link rel="stylesheet" href="/css/app.css"><script src="/js/app.js" defer></script></head>
<body class="dashboard-shell">
@php
$menus=\App\Support\SchoolAccess::menus(auth()->user(), \App\Support\School::config()['menus']);
$roleLabel=\App\Support\SchoolAccess::roleLabel(auth()->user());
$searchPlaceholder=__(match(auth()->user()->role){'keuangan'=>'Cari tagihan atau transaksi','akademik'=>'Cari siswa atau guru',default=>'Cari siswa, pendaftar, atau halaman'});
$schoolSettings=collect(\App\Support\CloudData::collection('dashboard_records'))->firstWhere('kind', 'settings');
$configuredYear=data_get($schoolSettings, 'data.year');
$schoolYear=is_string($configuredYear) && trim($configuredYear) !== '' ? $configuredYear : __('Belum diatur');
@endphp
<aside class="sidebar" id="dashboard-sidebar" aria-label="{{ __('Navigasi utama') }}"><div class="sidebar-brand-row"><a href="/admin" class="brand"><img src="/assets/logo.png" alt="Logo SD Ceria Nusantara"><span><strong>SD Ceria</strong><small>Nusantara</small></span></a><button type="button" class="header-icon-button sidebar-close" data-sidebar-close aria-label="{{ __('Tutup navigasi') }}"><x-icon name="close"/></button></div>
<div class="sidebar-inner"><p class="nav-label">{{ __('MENU UTAMA') }}</p>
<nav aria-label="Navigasi utama">@foreach($menus as $menu)
@if(!$menu[4])<a href="/admin" class="nav-item {{ ($group??'')==='' && ($screen??'')===''?'active':'' }}"><x-icon :name="$menu[2]"/><span>{{ session('locale')==='en'?$menu[1]:$menu[0] }}</span></a>
@else<details class="nav-group" @if(($group??'')===$menu[3]) open @endif><summary class="nav-item {{ ($group??'')===$menu[3]?'active':'' }}"><x-icon :name="$menu[2]"/><span>{{ session('locale')==='en'?$menu[1]:$menu[0] }}</span><x-icon name="chevron" class="chevron"/></summary><div class="subnav">@foreach($menu[4] as $child)<a href="/admin/{{ $child[2] }}" class="{{ ($screen??'')===$child[2]?'selected':'' }}">{{ session('locale')==='en'?$child[1]:$child[0] }}</a>@endforeach</div></details>@endif
@endforeach</nav><div class="sidebar-footer"><small>SD Ceria Nusantara</small><form method="post" action="/logout">@csrf<button class="text-link">{{ __('Keluar akun') }}</button></form></div></div></aside>
<button type="button" class="sidebar-backdrop" data-sidebar-backdrop aria-label="{{ __('Tutup navigasi') }}" hidden></button>
<x-dashboard-header :school-year="$schoolYear" :role-label="$roleLabel" :search-placeholder="$searchPlaceholder"/>
<main class="main"><x-dashboard-back :invoice-id="($invoice ?? null)?->id"/><div class="page-heading workspace-page-heading"><x-dashboard-breadcrumbs :title="$titleForm ?? $title ?? 'Beranda'" :screen="$screen ?? ''" :record="$record ?? null" :tab="$tab ?? null" :invoice-id="($invoice ?? null)?->id"/><h1>{{ __($titleForm ?? $title ?? 'Beranda') }}</h1><p class="page-school-year">{{ __('Tahun ajaran') }} <span>{{ $schoolYear }}</span></p></div>
@if(session('success'))<div class="flash success" role="status"><x-icon name="check"/>{{ session('success') }}<button aria-label="Tutup" data-dismiss>×</button></div>@endif
@if($errors->any())<div class="flash danger" role="alert"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<div class="dashboard-work-area"><div class="dashboard-content">@yield('content')</div></div>
</main><dialog id="confirm-dialog"><h2>Konfirmasi tindakan</h2><p id="confirm-message"></p><div class="actions"><button class="btn secondary" data-confirm-cancel>Batal</button><button class="btn" data-confirm-accept>Lanjutkan</button></div></dialog>
<x-photo-cropper/>
</body></html>
