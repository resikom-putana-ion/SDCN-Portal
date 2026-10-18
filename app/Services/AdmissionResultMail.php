<?php

namespace App\Services;

use App\Models\SchoolRecord;
use App\Support\CloudData;
use Illuminate\Support\Facades\Mail;

class AdmissionResultMail
{
    public function send(SchoolRecord $record): string
    {
        $record = $record->fresh();
        if (! in_array($record->value('status'), ['Lolos administrasi', 'Ditolak'])) return '';
        if (! config('mail.admissions.enabled') || ! filter_var(config('mail.admissions.from'), FILTER_VALIDATE_EMAIL)) {
            return ' Email hasil belum dikirim: pengirim no-reply belum dikonfigurasi.';
        }
        $email = $record->value('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return ' Email hasil belum dikirim: alamat email wali tidak valid.';
        $fingerprint = hash('sha256', json_encode([$email, $record->value('status'), $record->value('notes')]));
        if ($record->value('result_email.fingerprint') === $fingerprint) return ' Email hasil sudah dikirim.';
        try {
            Mail::send('emails.admission-result', ['record' => $record], function ($message) use ($record, $email) {
                $message->to($email)->from(config('mail.admissions.from'), config('mail.from.name'))
                    ->subject('Hasil administrasi PPDB '.$record->code.' · '.$record->value('status'));
            });
        } catch (\Throwable $exception) {
            report($exception);
            return ' Email hasil gagal dikirim. Simpan kembali keputusan untuk mencoba lagi.';
        }
        CloudData::transaction(function () use ($record, $fingerprint) {
            $current = SchoolRecord::findOrFail($record->id);
            $current->update(['data' => [...$current->data, 'result_email' => ['fingerprint' => $fingerprint, 'sent_at' => now()->toIso8601String()]]]);
        });
        return ' Email hasil telah dikirim.';
    }
}
