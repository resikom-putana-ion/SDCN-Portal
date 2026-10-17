<?php

namespace App\Support;

use Illuminate\Http\Request;

class DashboardNavigation
{
    // Keep only list controls, never arbitrary redirect destinations or form values.
    private const FILTERS = ['q', 'filter', 'year', 'tab', 'role', 'status', 'invoice', 'student', 'page'];

    public static function back(Request $request, ?string $invoiceId = null): ?array
    {
        $segments = explode('/', trim($request->path(), '/'));
        $screen = $segments[1] ?? null;
        if ($segments[0] !== 'admin' || !$screen) return null;

        $pages = School::config()['pages'];
        $lists = array_keys($pages);
        $lists = [...$lists, 'pengguna', 'pembayaran'];
        if ($request->isMethod('GET') && count($segments) === 2 && in_array($screen, $lists, true) && $request->hasSession()) {
            $request->session()->put('dashboard_navigation.lists.'.$screen, self::withFilters('/admin/'.$screen, $request->query()));
        }

        if (isset($pages[$screen]) || $screen === 'pengguna') {
            $id = $segments[2] ?? null;
            $tab = $segments[3] ?? null;
            if (!$id) return self::link('/admin', 'Kembali ke beranda');
            if (in_array($screen, ['siswa', 'guru'], true) && $id !== 'baru' && $tab && $tab !== 'detail') {
                return self::link('/admin/'.$screen.'/'.$id, 'Kembali ke profil');
            }
            $label = match ($screen) {
                'siswa' => 'Kembali ke daftar siswa',
                'guru' => 'Kembali ke daftar guru & staf',
                'pendaftar' => 'Kembali ke daftar pendaftar',
                'daftar-ulang' => 'Kembali ke daftar ulang',
                'pengguna' => 'Kembali ke daftar pengguna',
                default => 'Kembali ke '.mb_strtolower($pages[$screen][0]),
            };
            return self::listLink($request, $screen, $label);
        }

        if ($screen === 'website' && isset($segments[2])) {
            if ($segments[2] === 'preview') {
                $page = $request->query('page', 'home');
                if (in_array($page, ['privacy', 'terms'], true)) $page = 'settings';
                if (is_string($page) && isset(\App\Services\WebsiteContent::SECTIONS[$page])) {
                    return self::link('/admin/website/'.$page.'/edit', 'Kembali ke editor');
                }
            }
            return self::link('/admin/website', 'Kembali ke kelola website');
        }

        return match ($screen) {
            'halaman', 'media', 'publikasi', 'terbit' => self::link('/admin/website', 'Kembali ke kelola website'),
            'kuitansi', 'periksa-pembayaran' => self::listLink($request, 'pembayaran', 'Kembali ke pembayaran', $invoiceId ? ['invoice'=>$invoiceId] : []),
            'kenaikan' => self::listLink($request, 'kelas', 'Kembali ke daftar kelas'),
            'aktivasi' => self::listLink($request, 'daftar-ulang', 'Kembali ke daftar ulang'),
            'audit' => self::link('/admin/pengaturan', 'Kembali ke pengaturan'),
            default => self::link('/admin', 'Kembali ke beranda'),
        };
    }

    private static function listLink(Request $request, string $screen, string $label, array $context = []): array
    {
        $path = '/admin/'.$screen;
        $remembered = $request->hasSession() ? $request->session()->get('dashboard_navigation.lists.'.$screen) : null;
        foreach ([$request->headers->get('referer'), $remembered] as $candidate) {
            $url = self::safeListUrl($request, $candidate, $path);
            if ($url !== null) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $filters);
                return self::link(self::withFilters($path, [...$filters, ...$context]), $label);
            }
        }
        return self::link(self::withFilters($path, $context), $label);
    }

    private static function safeListUrl(Request $request, mixed $candidate, string $path): ?string
    {
        if (!is_string($candidate) || strlen($candidate) > 4000 || str_contains($candidate, '\\')) return null;
        $parts = parse_url($candidate);
        if ($parts === false || ($parts['path'] ?? '') !== $path || isset($parts['user']) || isset($parts['pass'])) return null;
        if (isset($parts['host'])) {
            $scheme = $parts['scheme'] ?? '';
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            if ($scheme !== $request->getScheme() || strcasecmp($parts['host'], $request->getHost()) !== 0 || $port !== $request->getPort()) return null;
        } elseif (!str_starts_with($candidate, '/') || str_starts_with($candidate, '//') || isset($parts['scheme'])) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        return self::withFilters($path, $query);
    }

    private static function withFilters(string $path, array $query): string
    {
        $filters = [];
        foreach (self::FILTERS as $key) {
            if (isset($query[$key]) && is_scalar($query[$key]) && strlen((string) $query[$key]) <= 500) $filters[$key] = (string) $query[$key];
        }
        return $path.($filters ? '?'.http_build_query($filters, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private static function link(string $url, string $label): array
    {
        return compact('url', 'label');
    }
}
