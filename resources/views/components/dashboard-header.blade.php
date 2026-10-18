@props(['schoolYear', 'roleLabel', 'searchPlaceholder'])
<header class="topbar workspace-header" aria-label="{{ __('Header dashboard') }}">
    <div class="workspace-header-surface">
        <button type="button" class="header-icon-button hamburger" data-sidebar aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="{{ __('Buka navigasi') }}" data-open-label="{{ __('Buka navigasi') }}" data-close-label="{{ __('Tutup navigasi') }}"><x-icon name="menu"/></button>
        <a href="/admin" class="header-mobile-brand">SD Ceria Nusantara</a>
        <form action="/admin/cari" class="header-search-shell" role="search" aria-label="{{ __('Pencarian global') }}">
            <label class="header-search"><x-icon name="search"/><input type="search" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ $searchPlaceholder }}" aria-label="{{ __('Cari data sesuai akses') }}" maxlength="200"></label>
        </form>
        <div class="header-year"><span>{{ __('Tahun ajaran') }}</span><strong data-header-year>{{ $schoolYear }}</strong></div>
        <span class="header-divider" aria-hidden="true"></span>
        <a href="/admin/notifikasi" class="header-icon-button header-notifications" data-notification-feed="/admin/notifikasi/feed" aria-label="{{ __('Notifikasi') }}" title="{{ __('Notifikasi') }}"><x-icon name="bell"/><span class="notification-count" data-notification-count hidden></span></a>
        <details class="header-account" data-account-menu>
            <summary aria-label="{{ __('Menu akun') }}: {{ auth()->user()->name }}" aria-controls="header-account-options" aria-expanded="false">
                <x-admin-avatar/>
                <span class="header-account-identity"><strong title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</strong><small>{{ __($roleLabel) }}</small></span>
                <x-icon name="chevron" class="header-account-chevron"/>
            </summary>
            <div class="header-account-panel" id="header-account-options">
                <div class="header-account-caption"><span>{{ __('Akun aktif') }}</span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email ?? '' }}</small></div>
                <a href="/admin/pengaturan"><x-icon name="users"/><span>{{ __('Profil & pengaturan akun') }}</span></a>
                <button type="button" data-theme aria-label="{{ __('Tema gelap') }}" aria-pressed="false"><x-icon name="moon"/><span data-theme-label data-light-label="{{ __('Tema: Terang') }}" data-dark-label="{{ __('Tema: Gelap') }}">{{ __('Tema: Terang') }}</span></button>
                <a href="/admin/bahasa"><x-icon name="globe"/><span>{{ __('Bahasa') }}: {{ session('locale', 'id') === 'en' ? 'English' : 'Indonesia' }}</span></a>
                <form method="post" action="/logout">@csrf<button class="header-signout"><x-icon name="logout"/><span>{{ __('Keluar akun') }}</span></button></form>
            </div>
        </details>
    </div>
</header>
