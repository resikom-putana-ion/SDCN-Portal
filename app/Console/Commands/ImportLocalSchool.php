<?php

namespace App\Console\Commands;

use App\Services\FirestoreDocumentStore;
use App\Services\FirestoreFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ImportLocalSchool extends Command
{
    protected $signature = 'school:import-local {--database=} {--apply : Salin data tanpa menimpa dokumen cloud}';
    protected $description = 'Impor satu kali database lama; koneksi runtime tetap Firestore';

    public function handle(FirestoreDocumentStore $store, FirestoreFileStorage $files): int
    {
        if (!app()->environment('local', 'testing')) { $this->error('Impor hanya tersedia secara lokal.'); return 1; }
        $path = $this->option('database') ?: database_path('database.sqlite');
        if (!is_file($path)) { $this->error('Database sumber tidak ditemukan.'); return 1; }
        $source = new \PDO('sqlite:'.$path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only = ON');
        $tables = [];
        foreach (['users', 'school_records', 'school_payments', 'school_documents', 'admission_activations'] as $table) {
            $tables[$table] = $source->query('SELECT * FROM '.$table)->fetchAll(\PDO::FETCH_ASSOC);
            $this->line($table.': '.count($tables[$table]));
        }
        if (!$this->option('apply')) { $this->info('Pratinjau selesai. Gunakan --apply untuk menyalin.'); return 0; }
        $contentBefore = \App\Services\WebsiteContent::fingerprint($store->all('content'));
        $backup = storage_path('app/private/pre-firestore-'.now()->format('Ymd-His').'.sqlite');
        copy($path, $backup);
        $userIds = []; $recordIds = []; $created = 0;
        foreach ($tables['users'] as $row) {
            $email = mb_strtolower(trim($row['email']));
            $id = hash('sha256', $email); $userIds[$row['id']] = $id;
            $demo = Hash::check('Ceria2026!', $row['password']);
            $created += (int) $store->create('admins', $id, [
                'email' => $email, 'name' => $row['name'], 'password' => $row['password'],
                'role' => $row['role'] ?? 'admin', 'active' => !$demo, 'remember_token' => '',
                'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            ]);
        }
        foreach ($tables['school_records'] as $row) $recordIds[$row['id']] = $row['kind'] === 'applicants' ? $row['code'] : (string) $row['id'];
        foreach ($tables['school_records'] as $row) {
            $row['data'] = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            $id = $recordIds[$row['id']];
            unset($row['id']);
            foreach (['student_id', 'applicant_id'] as $key) if (isset($row['data'][$key])) $row['data'][$key] = $recordIds[$row['data'][$key]] ?? (string) $row['data'][$key];
            if ($row['kind'] === 'content') {
                $created += (int) $store->create('dashboard_legacy_content', $id, $row);
                continue;
            }
            if ($row['kind'] === 'applicants') {
                $data = $row['data'];
                $status = in_array($data['status'] ?? '', ['Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan', 'Aktif']) ? 'diterima' : 'baru';
                $created += (int) $store->create('applications', $id, [
                    'reference' => $row['code'], 'child' => ['child_name' => $data['name'], 'birth_date' => $data['birth_date'] ?? '', 'gender' => $data['gender'] ?? '', 'previous_school' => $data['previous_school'] ?? ''],
                    'parent' => ['parent_name' => $data['guardian'] ?? '', 'relationship' => $data['relationship'] ?? '', 'phone' => $data['phone'] ?? '', 'email' => $data['email'] ?? '', 'address' => $data['address'] ?? ''],
                    'status' => $status, 'admin_notes' => $data['notes'] ?? '', 'dashboard' => [...$data, 'source_status' => $status],
                    'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'], 'source' => 'dashboard-local-import',
                ]);
            } else {
                if ($row['kind'] === 'media' && str_starts_with($row['data']['image'] ?? '', 'uploads/')) {
                    $sourceFile = public_path('assets/'.$row['data']['image']);
                    if (!is_file($sourceFile)) throw new \RuntimeException('Berkas media lama tidak ditemukan.');
                    $filePath = 'media/'.basename($sourceFile);
                    if (!$files->exists($filePath)) $files->put($filePath, file_get_contents($sourceFile), mime_content_type($sourceFile));
                    $row['data']['image'] = '/'.$filePath;
                }
                $created += (int) $store->create('dashboard_records', $id, $row);
            }
        }
        foreach (['school_payments', 'school_documents', 'admission_activations'] as $table) foreach ($tables[$table] as $row) {
            $id = (string) $row['id']; unset($row['id']);
            foreach (['record_id', 'invoice_id', 'student_id', 'applicant_id'] as $key) if (isset($row[$key])) $row[$key] = $recordIds[$row[$key]] ?? (string) $row[$key];
            foreach (['user_id', 'verified_by'] as $key) if (isset($row[$key])) $row[$key] = $userIds[$row[$key]] ?? (string) $row[$key];
            if ($table === 'school_documents') {
                $sourceFile = storage_path('app/private/'.$row['path']);
                if (!is_file($sourceFile)) throw new \RuntimeException('Berkas privat lama tidak ditemukan; impor dihentikan.');
                if (!$files->exists($row['path'])) $files->put($row['path'], file_get_contents($sourceFile), $row['mime']);
            }
            $created += (int) $store->create($table, $id, $row);
        }
        $this->info($created.' dokumen baru tersalin. Konten website dan akun cloud yang sudah ada tidak ditimpa.');
        if (!hash_equals($contentBefore, \App\Services\WebsiteContent::fingerprint($store->all('content')))) {
            $this->warn('Konten website berubah selama impor; impor tidak menulis koleksi content. Periksa perubahan dari editor lain.');
        } else $this->line('Verifikasi: isi delapan bagian website tetap sama.');
        $this->line('Akun dengan kata sandi demo diimpor dalam keadaan nonaktif. Gunakan akun pengelola Firestore yang sudah ada.');
        return 0;
    }
}
