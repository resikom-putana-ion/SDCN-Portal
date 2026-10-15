<?php

namespace App\Console\Commands;

use App\Exceptions\FirebaseAuthException;
use App\Services\FirebaseAccountMigration;
use App\Services\FirebaseAuth;
use App\Support\CloudData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class MigrateFirebaseAuth extends Command
{
    protected $signature = 'school:firebase-auth {--apply : Migrasikan akun dan aktifkan Firebase Authentication}';
    protected $description = 'Periksa atau migrasikan akun sekolah ke Firebase Authentication tanpa mengganti kata sandi';

    public function handle(FirebaseAuth $auth, FirebaseAccountMigration $migration): int
    {
        try {
            $configuration = $auth->configuration();
            if (! ($configuration['signIn']['email']['enabled'] ?? false)) {
                $this->error('Aktifkan Email/Password di Firebase Console > Authentication > Sign-in method.');
                return self::FAILURE;
            }
            $key = config('school.firebase_api_key') ?: ($configuration['client']['apiKey'] ?? null);
            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
                $this->error('Web API Key proyek belum tersedia. Isi FIREBASE_API_KEY di .env.');
                return self::FAILURE;
            }
            $rows = CloudData::store()->all('admins');
            foreach ($rows as $row) $migration->inspect($row);
            $this->info('Email/Password aktif. '.count($rows).' akun siap diperiksa/dimigrasikan; role tetap tersimpan di Firestore.');
            if (! $this->option('apply')) {
                $this->line('Pratinjau selesai, belum ada perubahan. Jalankan dengan --apply untuk migrasi dan aktivasi.');
                return self::SUCCESS;
            }
            $path = $this->laravel->environmentFilePath();
            if (! is_file($path) || ! is_writable($path)) {
                $this->error('.env tidak ditemukan atau tidak dapat ditulis.');
                return self::FAILURE;
            }
            // Encrypted recovery snapshot before removing the old password hashes.
            $directory = storage_path('app/private/auth-migration');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) return self::FAILURE;
            $backup = $directory.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.enc';
            if (file_put_contents($backup, Crypt::encryptString(json_encode($rows, JSON_THROW_ON_ERROR)), LOCK_EX) === false) return self::FAILURE;
            $this->writeEnvironment($path, ['FIREBASE_API_KEY' => $key]);
            config(['school.firebase_api_key' => $key]);
            foreach ($rows as $row) {
                if (empty($row['firebase_uid']) || ! empty($row['password'])) $migration->migrate($row);
            }
            $this->writeEnvironment($path, ['FIREBASE_AUTH_ENABLED' => 'true']);
            $this->callSilent('config:clear');
            $this->info('Firebase Authentication aktif. '.count($rows).' akun terhubung. Kata sandi lama tetap dapat digunakan.');
            $this->line('Sesi lama perlu login ulang. Pemulihan dikirim oleh Firebase; setup Gmail tidak diperlukan.');
            return self::SUCCESS;
        } catch (FirebaseAuthException $e) {
            $this->error('Migrasi belum selesai: '.$e->reason.'. Akun Firebase yang sudah ada tidak ditimpa. Perbaiki penyebabnya lalu jalankan ulang.');
            return self::FAILURE;
        }
    }

    private function writeEnvironment(string $path, array $values): void
    {
        $contents = file_get_contents($path);
        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) throw new FirebaseAuthException('ENV_WRITE_FAILED');
    }
}
