<?php

namespace App\Support;

class AdmissionPayment
{
    public static function method(array $row): ?string
    {
        $value = $row['payment_method'] ?? data_get($row, 'payment.method') ?? data_get($row, 'dashboard.payment_method');
        return match (is_string($value) ? strtolower(trim($value)) : '') {
            'offline', 'cash', 'tunai', 'tunai di sekolah', 'di sekolah' => 'offline',
            'online', 'transfer', 'bank_transfer', 'transfer rekening', 'transfer di sekolah' => 'transfer',
            default => null,
        };
    }

    public static function label(?string $method): string
    {
        return match ($method) {
            'offline' => 'Offline · Bayar di sekolah',
            'transfer' => 'Online · Transfer rekening',
            default => 'Belum tersedia dari pendaftaran',
        };
    }
}
