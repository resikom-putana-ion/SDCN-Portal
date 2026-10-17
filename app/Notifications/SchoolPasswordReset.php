<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class SchoolPasswordReset extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Use the configured website address, never the incoming request's Host header.
        $url = rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->mailer('smtp')
            ->subject('Atur ulang kata sandi — '.config('app.name'))
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun sekolah Anda.')
            ->action('Buat kata sandi baru', $url)
            ->line("Tautan ini berlaku selama {$minutes} menit dan hanya dapat digunakan sekali.")
            ->line('Setelah menyimpan kata sandi baru, kembali ke halaman masuk untuk login.')
            ->line('Jika Anda tidak meminta pemulihan akun, abaikan email ini. Kata sandi Anda belum berubah.')
            ->salutation('Salam, '.config('app.name'));
    }
}
