<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SchoolAccess
{
    private const ROLE_KINDS = [
        'keuangan' => ['invoices', 'transactions'],
        'akademik' => ['students', 'staff', 'classes', 'attendance', 'grades'],
    ];

    private const SIMPLE_SCREENS = [
        'keuangan' => ['pembayaran', 'laporan', 'pengaturan', 'bahasa', 'notifikasi', 'cari'],
        'akademik' => ['kenaikan', 'laporan', 'pengaturan', 'bahasa', 'notifikasi', 'cari'],
    ];

    private const RECORD_SCREENS = [
        'keuangan' => ['tagihan', 'kas'],
        'akademik' => ['siswa', 'guru', 'kelas', 'absensi', 'nilai'],
    ];

    public static function hasFullAccess(?User $user): bool
    {
        return $user?->hasFullSchoolAccess() === true;
    }

    public static function roleLabel(?User $user): string
    {
        return User::ROLES[$user?->role] ?? 'Pengelola sekolah';
    }

    public static function kinds(?User $user): array
    {
        return self::hasFullAccess($user) ? array_keys(School::config()['forms']) : (self::ROLE_KINDS[$user?->role] ?? []);
    }

    public static function reportKinds(?User $user): array
    {
        return match ($user?->role) {
            'keuangan' => ['invoices'],
            'akademik' => ['students', 'attendance', 'grades'],
            default => ['students', 'applicants', 'attendance', 'grades', 'invoices'],
        };
    }

    public static function searchKinds(?User $user): array
    {
        return match ($user?->role) {
            'keuangan' => ['invoices', 'transactions'],
            'akademik' => ['students', 'staff'],
            default => ['students', 'staff', 'applicants', 'content'],
        };
    }

    public static function allowsKind(?User $user, string $kind): bool
    {
        return self::hasFullAccess($user) || in_array($kind, self::ROLE_KINDS[$user?->role] ?? [], true);
    }

    public static function allowsScreen(?User $user, string $screen, ?string $id, ?string $tab, Request $request): bool
    {
        if (self::hasFullAccess($user)) {
            return true;
        }

        $role = (string) $user?->role;
        if (in_array($screen, self::SIMPLE_SCREENS[$role] ?? [], true)) {
            if ($id !== null || $tab !== null) {
                return false;
            }

            return $screen !== 'pengaturan' || $request->query('tab') !== 'sekolah';
        }

        if (! in_array($screen, self::RECORD_SCREENS[$role] ?? [], true)) {
            return false;
        }

        if ($id === null || $id === 'baru') {
            return $tab === null;
        }

        if ($role === 'akademik' && $screen === 'siswa') {
            return in_array($tab, [null, 'detail', 'kehadiran', 'nilai', 'dokumen', 'edit'], true);
        }

        if ($role === 'akademik' && $screen === 'guru') {
            return in_array($tab, [null, 'detail', 'jadwal', 'kehadiran', 'cuti', 'dokumen', 'edit'], true);
        }

        return in_array($tab, [null, 'edit'], true);
    }

    public static function menus(?User $user, array $menus): array
    {
        if (self::hasFullAccess($user)) {
            return $menus;
        }

        return collect($menus)->map(function (array $menu) use ($user) {
            if (empty($menu[4])) {
                return $menu;
            }

            $menu[4] = array_values(array_filter($menu[4], function (array $child) use ($user) {
                $screen = explode('/', $child[2], 2)[0];

                return in_array($screen, [...(self::SIMPLE_SCREENS[$user?->role] ?? []), ...(self::RECORD_SCREENS[$user?->role] ?? [])], true);
            }));

            return $menu;
        })->filter(fn (array $menu) => empty($menu[3]) || ! empty($menu[4]))->values()->all();
    }
}
