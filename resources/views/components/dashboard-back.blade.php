@props(['invoiceId'=>null])
@php($backNavigation = \App\Support\DashboardNavigation::back(request(), $invoiceId))
@if($backNavigation)
<nav class="page-back no-print" aria-label="{{ __('Navigasi kembali') }}"><a href="{{ $backNavigation['url'] }}" data-dashboard-back><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 5-7 7 7 7M3 12h18"/></svg><span>{{ __($backNavigation['label']) }}</span></a></nav>
@endif
