<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SetupGmail extends Command
{
    protected $signature = 'school:setup-gmail';

    protected $description = 'Hubungkan Gmail untuk pemulihan akun dan kirim email uji ke pengirim';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Jalankan di terminal interaktif agar App Password dapat dimasukkan secara tersembunyi.');

            return self::FAILURE;
        }

        $this->info('Aktifkan Verifikasi 2 Langkah, lalu buat App Password di https://myaccount.google.com/apppasswords');
        $this->line('Gunakan App Password 16 karakter. Jangan gunakan kata sandi login Gmail.');
        $email = trim((string) $this->ask('Email Gmail / Google Workspace pengirim'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strpbrk($email, "'\"\\\r\n\$") !== false) {
            $this->error('Alamat email pengirim tidak valid.');

            return self::FAILURE;
        }
        $password = preg_replace('/\s+/', '', (string) $this->secret('App Password Gmail (input disembunyikan)', false));
        if (! preg_match('/^[a-zA-Z0-9]{16}$/', $password)) {
            $this->error('App Password harus terdiri dari 16 karakter. Salin dari pengaturan Akun Google.');

            return self::FAILURE;
        }

        $path = $this->laravel->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            $this->error('Berkas .env tidak ditemukan atau tidak dapat ditulis.');

            return self::FAILURE;
        }
        $contents = file_get_contents($path);
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $values = [
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtps', 'MAIL_URL' => 'null',
            'MAIL_HOST' => 'smtp.gmail.com', 'MAIL_PORT' => '465',
            'MAIL_USERNAME' => $email, 'MAIL_PASSWORD' => $password, 'MAIL_FROM_ADDRESS' => $email,
        ];
        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents)
                : rtrim($contents, "\r\n").$newline.$line.$newline;
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            $this->error('Konfigurasi Gmail gagal disimpan.');

            return self::FAILURE;
        }
        $this->callSilent('config:clear');
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 465, 'mail.mailers.smtp.username' => $email,
            'mail.mailers.smtp.password' => $password, 'mail.from.address' => $email,
        ]);
        Mail::purge('smtp');
        $this->info('Konfigurasi Gmail tersimpan. Mengirim email uji ke alamat pengirim...');
        try {
            Mail::mailer('smtp')->raw('Koneksi email '.config('app.name').' berhasil digunakan. Anda dapat meminta tautan pemulihan melalui halaman Lupa kata sandi.', function ($message) use ($email) {
                $message->to($email)->subject('Uji email pemulihan — '.config('app.name'));
            });
        } catch (TransportExceptionInterface) {
            $this->error('Gmail belum menerima email uji. Periksa App Password, izin akun Google Workspace, koneksi internet, dan akses SMTP port 465. Konfigurasi sudah tersimpan; jalankan kembali setelah diperbaiki.');

            return self::FAILURE;
        }
        $this->info('Gmail menerima email uji. Periksa kotak masuk/Spam, lalu coba halaman /forgot-password dengan email akun sekolah yang aktif.');
        $this->line('Jika server sudah berjalan, hentikan dan jalankan kembali run.bat agar konfigurasi terbaru dimuat.');

        return self::SUCCESS;
    }
}
