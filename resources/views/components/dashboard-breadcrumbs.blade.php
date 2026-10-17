@props(['title', 'screen'=>'', 'record'=>null, 'tab'=>null, 'invoiceId'=>null])
@php
$back = \App\Support\DashboardNavigation::back(request(), $invoiceId);
$parent = $back && $back['url'] !== '/admin' ? $back : null;
$parentLabel = $parent ? mb_convert_case(preg_replace('/^Kembali ke /u', '', $parent['label']), MB_CASE_TITLE, 'UTF-8') : null;
$pages = \App\Support\School::config()['pages'];
if ($parent && parse_url($parent['url'], PHP_URL_PATH) === '/admin/'.$screen && isset($pages[$screen])) $parentLabel = $pages[$screen][0];
if ($parent && $record && in_array($screen, ['siswa','guru'], true) && parse_url($parent['url'], PHP_URL_PATH) === '/admin/'.$screen.'/'.$record->id) $parentLabel = $record->value('name') ?: $record->code;
$current = in_array($screen, ['siswa','guru'], true) && $tab && !in_array($tab, ['detail','edit'], true) ? (['kehadiran'=>'Kehadiran','tagihan'=>'SPP & tagihan','nilai'=>'Nilai & rapor','dokumen'=>'Dokumen','jadwal'=>'Jadwal mengajar','cuti'=>'Cuti'][$tab] ?? $title) : $title;
@endphp
<nav class="breadcrumb workspace-breadcrumb" aria-label="{{ __('Jejak navigasi') }}">
    <ol>
        <li><a href="/admin">{{ __('Beranda') }}</a></li>
        @if($parent)
            <li><a href="{{ $parent['url'] }}">{{ __($parentLabel) }}</a></li>
        @endif
        @if(request()->path() !== 'admin')
            <li aria-current="page">{{ __($current) }}</li>
        @endif
    </ol>
</nav>
