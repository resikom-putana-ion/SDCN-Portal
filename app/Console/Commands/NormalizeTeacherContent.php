<?php

namespace App\Console\Commands;

use App\Services\FirestoreCacheStore;
use App\Services\FirestoreDocumentStore;
use App\Services\TeacherDirectory;
use App\Services\WebsiteContent;
use Illuminate\Console\Command;

class NormalizeTeacherContent extends Command
{
    protected $signature = 'school:normalize-teachers {--apply : Terapkan setelah mencadangkan dokumen yang berubah}';
    protected $description = 'Satukan profil website dengan direktori struktur organisasi, termasuk draf';

    public function handle(FirestoreDocumentStore $store): int
    {
        $before = $after = [];
        foreach (['content', 'dashboard_content_drafts'] as $collection) {
            foreach (['teachers', 'home'] as $id) {
                $document = $store->get($collection, $id);
                $key = $collection.'/'.$id;
                $before[$key] = $document;
                if (!$document) { $after[$key] = null; continue; }
                $normalized = $document;
                if ($collection === 'content') $normalized = $this->normalize($id, $document);
                else foreach (['base', 'data'] as $field) {
                    $normalized[$field] = $this->normalize($id, $document[$field]);
                }
                $after[$key] = $normalized;
            }
        }
        if (!$before['content/teachers']) { $this->error('Dokumen content/teachers tidak tersedia.'); return 1; }
        $changed = array_keys(array_filter($after, fn ($document, $key) =>
            WebsiteContent::fingerprint([$document]) !== WebsiteContent::fingerprint([$before[$key]]), ARRAY_FILTER_USE_BOTH));
        $this->line('Kepala sekolah: '.TeacherDirectory::principal($after['content/teachers'])['name']);
        $this->line('Dokumen berubah: '.(implode(', ', $changed) ?: 'tidak ada'));
        if (!$changed || !$this->option('apply')) return 0;

        $directory = storage_path('app/private/content-backups');
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new \RuntimeException('Folder cadangan tidak dapat dibuat.');
        $backup = $directory.'/teacher-profiles-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $json = json_encode(['project'=>config('school.firebase_project'), 'before'=>$before, 'after'=>$after],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json)) throw new \RuntimeException('Cadangan gagal disimpan.');

        $store->transaction(function () use ($store, $before, $after, $changed) {
            foreach ($before as $key => $expected) {
                [$collection, $id] = explode('/', $key, 2);
                if (WebsiteContent::fingerprint([$store->get($collection, $id)]) !== WebsiteContent::fingerprint([$expected])) {
                    throw new \RuntimeException('Konten berubah saat migrasi. Jalankan pratinjau kembali; perubahan belum diterapkan.');
                }
            }
            foreach ($changed as $key) {
                [$collection, $id] = explode('/', $key, 2);
                $store->put($collection, $id, $after[$key]);
            }
            (new FirestoreCacheStore($store, config('school.landing_cache_prefix')))->forget('school-content-firestore');
        });
        $this->info('Profil berhasil disatukan. Cadangan: '.$backup);
        return 0;
    }

    private function normalize(string $id, array $document): array
    {
        if ($id === 'teachers') {
            $document = TeacherDirectory::withDefaults($document);
            TeacherDirectory::validate($document);
        } else unset($document['principal_name']);
        return $document;
    }
}
