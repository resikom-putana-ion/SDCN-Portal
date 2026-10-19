<?php

namespace App\Services;

use App\Models\SchoolRecord;
use App\Models\User;
use App\Support\School;
use App\Support\SchoolAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SchoolNotificationFeed
{
    public const CATEGORIES = ['pendaftaran' => 'Pendaftaran', 'tagihan' => 'Tagihan',
        'pembayaran' => 'Pembayaran', 'kas' => 'Kas', 'akademik' => 'Akademik',
        'website' => 'Website', 'pengaturan' => 'Pengaturan', 'lainnya' => 'Lainnya'];

    public function cachedForUser(User $user): Collection
    {
        $key = 'notifications:v1:'.hash('sha256', json_encode([
            config('school.firebase_project'), config('school.firebase_database'),
            (string) $user->role, SchoolAccess::hasFullAccess($user),
        ]));
        // Keep this cache local so reading it never needs a Firestore round trip.
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file')
            ->remember($key, 20, fn () => $this->forUser($user));
    }

    public function forUser(User $user): Collection
    {
        $records = new NotificationRecords;
        $recent = $records->records([['created_at', 'GREATER_THAN_OR_EQUAL', now()->subDays(7)->toIso8601String()]]);
        $notifications = collect();
        $pendingPaymentEvents = [];
        $fullAccess = SchoolAccess::hasFullAccess($user);
        $role = (string) $user->role;

        // Admission documents are also written directly by the connected landing page.
        // Keep the alert generic for roles that cannot open private applicant profiles.
        foreach ($records->applicants() as $applicant) {
            $status = (string) $applicant->value('status');
            if (! in_array($status, ['Pemeriksaan', 'Perlu perbaikan', 'Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan'], true)) {
                continue;
            }

            $label = match ($status) {
                'Perlu perbaikan' => 'Pendaftar perlu perbaikan',
                'Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan' => 'Daftar ulang menunggu tindak lanjut',
                default => 'Pendaftar baru masuk',
            };
            $title = $fullAccess ? $label.': '.$applicant->value('name') : $label;
            $date = $applicant->created_at ?: $applicant->value('date');
            $description = 'Nomor '.$applicant->registrationNumber();
            if ($date) $description .= ' · '.School::date($date);

            $this->push($notifications, 'applicant-'.$applicant->id, $title, $description,
                $fullAccess ? '/admin/pendaftar/'.$applicant->id : null, $date, 3);
        }

        if ($fullAccess || $role === 'keuangan') {
            $invoices = $records->records([['data.due_date', 'LESS_THAN_OR_EQUAL', now()->addDays(7)->toDateString()]])
                ->where('kind', 'invoices')->keyBy(fn (SchoolRecord $invoice) => (string) $invoice->id);
            $paidByInvoice = $records->paidByInvoice($invoices);

            foreach ($records->payments([['status', 'EQUAL', 'Menunggu']]) as $payment) {
                $pendingPaymentEvents[] = 'Mencatat pembayaran '.$payment->reference;
                $invoice = $invoices->get((string) $payment->invoice_id) ?? $records->find($payment->invoice_id, 'invoices');
                $invoiceName = $invoice?->value('name', 'Tagihan sekolah') ?? 'Tagihan sekolah';
                $description = $payment->reference.' · '.$invoiceName.' · '.School::money($payment->amount);
                $this->push($notifications, 'payment-'.$payment->id, 'Transfer menunggu verifikasi', $description,
                    '/admin/periksa-pembayaran/'.$payment->id, $payment->created_at ?? $payment->received_at ?? null, 4);
            }

            $today = now()->startOfDay();
            $lastReminderDay = $today->copy()->addDays(7);
            foreach ($invoices as $invoice) {
                $dueDate = trim((string) $invoice->value('due_date'));
                if ($dueDate === '') continue;
                try {
                    $due = Carbon::parse($dueDate)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }

                $balance = max(0, (int) $invoice->value('amount') - (int) $paidByInvoice->get((string) $invoice->id, 0));
                if ($balance === 0 || $due->greaterThan($lastReminderDay)) continue;

                if ($due->lessThan($today)) {
                    $title = 'Tagihan melewati jatuh tempo';
                    $priority = 5;
                } elseif ($due->equalTo($today)) {
                    $title = 'Tagihan jatuh tempo hari ini';
                    $priority = 4;
                } else {
                    $title = 'Tagihan segera jatuh tempo';
                    $priority = 2;
                }

                $description = $invoice->value('name').' · Jatuh tempo '.School::date($dueDate).' · Sisa '.School::money($balance);
                $this->push($notifications, 'invoice-'.$invoice->id, $title, $description,
                    '/admin/pembayaran?invoice='.$invoice->id, $dueDate, $priority);
            }

            foreach ($recent->where('kind', 'transactions') as $transaction) {
                if (! $this->isRecent($transaction->created_at)) continue;
                $description = $transaction->value('type').' · '.School::money($transaction->value('amount', 0));
                $this->push($notifications, 'transaction-'.$transaction->id, 'Transaksi kas baru', $description,
                    '/admin/kas', $transaction->created_at, 1);
            }
        }

        if ($fullAccess || $role === 'akademik') {
            foreach ($recent->where('kind', 'students') as $student) {
                if (! $this->isRecent($student->created_at)) continue;
                $description = $student->code.' · Kelas '.$student->value('class', 'belum ditempatkan');
                $this->push($notifications, 'student-'.$student->id, 'Siswa baru tercatat', $description,
                    '/admin/siswa/'.$student->id, $student->created_at, 2);
            }

            foreach ($records->records([['kind', 'EQUAL', 'grades'], ['data.status', 'EQUAL', 'Pemeriksaan']]) as $grade) {
                $student = $records->find($grade->value('student_id'), 'students');
                $studentName = $student?->value('name', 'Siswa') ?? 'Siswa';
                $description = $studentName.' · '.$grade->value('subject').' · '.$grade->value('semester');
                $this->push($notifications, 'grade-'.$grade->id, 'Nilai menunggu pemeriksaan', $description,
                    '/admin/nilai/'.$grade->id, $grade->created_at, 3);
            }

            foreach ($records->records([['data.date', 'EQUAL', now()->toDateString()]])->where('kind', 'attendance')->filter(fn ($row) => $row->value('status') === 'Alpa') as $attendance) {
                $student = $records->find($attendance->value('student_id'), 'students');
                $description = ($student?->value('name', 'Siswa') ?? 'Siswa').' · '.School::date($attendance->value('date'));
                $this->push($notifications, 'attendance-'.$attendance->id, 'Absensi tanpa keterangan hari ini', $description,
                    '/admin/absensi/'.$attendance->id, $attendance->created_at ?? $attendance->value('date'), 3);
            }
        }

        foreach ($recent->where('kind', 'activities') as $activity) {
            if (! $this->isRecent($activity->created_at)) continue;
            $section = mb_strtolower(trim((string) $activity->value('section')));
            $name = (string) $activity->value('name');
            $status = (string) $activity->value('status');

            // The current applicant/payment/academic queues already represent these events.
            if (($section === 'ppdb' && str_starts_with(mb_strtolower($name), 'pendaftaran baru'))
                || in_array($section, ['transactions', 'grades', 'attendance', 'students'], true)
                || ($section === 'pembayaran' && in_array($name, $pendingPaymentEvents, true))) {
                continue;
            }

            $destination = null;
            if (in_array($section, ['ppdb', 'penerimaan siswa'], true)) {
                if (! $fullAccess && ! in_array($role, ['keuangan', 'akademik'], true)) continue;
                $title = 'Pembaruan proses PPDB';
                $description = 'Status: '.$status;
                if ($fullAccess) $description = $name.' · '.$status;
                if ($fullAccess) $destination = '/admin/pendaftar';
            } elseif (in_array($section, ['pembayaran', 'invoices', 'transactions'], true)) {
                if (! $fullAccess && $role !== 'keuangan') continue;
                $title = $section === 'pembayaran' ? 'Pembaruan pembayaran' : 'Data keuangan diperbarui';
                $description = ($fullAccess ? $name.' · ' : '').$status;
                $destination = '/admin/pembayaran';
            } elseif (in_array($section, ['akademik', 'siswa', 'dokumen', 'classes', 'staff'], true)) {
                if (! $fullAccess && $role !== 'akademik') continue;
                $title = 'Pembaruan data akademik';
                $description = ($fullAccess ? $name.' · ' : '').$status;
                $destination = '/admin/laporan';
            } elseif (in_array($section, ['website', 'users', 'settings', 'period', 'pengaturan'], true)) {
                if (! $fullAccess) continue;
                $title = match ($section) {
                    'website' => 'Pembaruan website sekolah',
                    'users' => 'Pembaruan akun pengelola',
                    default => 'Pengaturan sekolah diperbarui',
                };
                $description = $name.' · '.$status;
                $destination = match ($section) {
                    'website' => '/admin/publikasi',
                    'users' => '/admin/pengguna',
                    default => '/admin/pengaturan',
                };
            } else {
                if (! $fullAccess) continue;
                $title = 'Data sekolah diperbarui';
                $description = $name.' · '.$section.' · '.$status;
                $destination = '/admin';
            }

            $this->push($notifications, 'activity-'.$activity->id, $title, $description,
                $destination, $activity->created_at, 1, match ($section) {
                    'ppdb', 'penerimaan siswa' => 'pendaftaran',
                    'invoices' => 'tagihan', 'pembayaran' => 'pembayaran', 'transactions' => 'kas',
                    'akademik', 'siswa', 'dokumen', 'classes', 'staff' => 'akademik',
                    'website' => 'website', 'users', 'settings', 'period', 'pengaturan' => 'pengaturan',
                    default => 'lainnya',
                });
        }

        return $notifications->sort(function (array $left, array $right) {
            return [$right['priority'], $right['sort_at']] <=> [$left['priority'], $left['sort_at']];
        })->take(100)->values()->map(fn (array $item) => collect($item)->except(['priority', 'sort_at'])->all());
    }

    private function push(Collection $notifications, string $id, string $title, string $description, ?string $href, mixed $date, int $priority, ?string $category = null): void
    {
        try {
            $timestamp = $date ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            $timestamp = now();
        }

        $notifications->push([
            'id' => $id,
            'category' => $category ?? match (strstr($id, '-', true)) {
                'applicant' => 'pendaftaran', 'invoice' => 'tagihan', 'payment' => 'pembayaran',
                'transaction' => 'kas', 'student', 'grade', 'attendance' => 'akademik', default => 'lainnya',
            },
            'title' => $title,
            'description' => $description,
            'href' => $href,
            'time' => $timestamp->locale('id')->translatedFormat('j M Y · H:i'),
            'priority' => $priority,
            'sort_at' => $timestamp->getTimestamp(),
        ]);
    }

    private function isRecent(mixed $date): bool
    {
        if (! $date) return false;
        try {
            return Carbon::parse($date)->greaterThanOrEqualTo(now()->subDays(7));
        } catch (\Throwable) {
            return false;
        }
    }
}
