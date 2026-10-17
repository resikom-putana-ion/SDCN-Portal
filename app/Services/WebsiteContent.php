<?php

namespace App\Services;

use App\Support\CloudData;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WebsiteContent
{
    public const SECTIONS = ['settings' => 'Informasi Sekolah & PPDB', 'home' => 'Beranda', 'about' => 'Profil Sekolah', 'program' => 'Program', 'facilities' => 'Fasilitas', 'teachers' => 'Guru & Staf', 'gallery' => 'Berita & Galeri', 'contact' => 'Kontak'];
    public function section(string $section): string
    {
        $section = ['hero' => 'home', 'profil' => 'about', 'fasilitas' => 'facilities', 'guru' => 'teachers', 'kontak' => 'contact', 'ppdb' => 'settings'][$section] ?? $section;
        abort_unless(isset(self::SECTIONS[$section]), 404);
        return $section;
    }
    public function clean(array $data): array { unset($data['id']); return $data; }
    public static function fingerprint(array $data): string
    {
        $normalize = static function ($value) use (&$normalize) {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($normalize, $value);
        };
        return hash('sha256', serialize($normalize($data)));
    }
    public function all(bool $draft = false): array
    {
        $all = [];
        foreach (CloudData::collection('content') as $row) if (isset(self::SECTIONS[$row['id']])) $all[$row['id']] = $this->clean($row);
        if ($draft) foreach (CloudData::collection('dashboard_content_drafts') as $row) {
            if (isset($all[$row['id']])) $all[$row['id']] = $this->mergeDraft($all[$row['id']], $row, false);
        }
        if (isset($all['teachers'])) $all['teachers'] = TeacherDirectory::withDefaults($all['teachers']);
        return $all;
    }
    public function editor(string $section): array
    {
        $published = CloudData::store()->get('content', $section);
        abort_unless($published, 404, 'Bagian website belum tersedia di Firestore.');
        $published = $this->clean($published);
        $draft = CloudData::store()->get('dashboard_content_drafts', $section);
        $data = $draft ? $this->mergeDraft($published, $draft, false) : $published;
        if ($section === 'teachers') $data = TeacherDirectory::withDefaults($data);
        return ['published' => $published, 'data' => $data,
            'version' => self::fingerprint([$published, $draft]), 'draft' => $draft];
    }
    public function save(string $section, array $data, string $version): void
    {
        CloudData::transaction(function () use ($section, $data, $version) {
            $current = $this->editor($section);
            if (!hash_equals($current['version'], $version)) throw ValidationException::withMessages(['content' => 'Konten berubah sejak formulir dibuka. Muat ulang halaman sebelum menyimpan.']);
            CloudData::store()->put('dashboard_content_drafts', $section, [
                'base' => $current['published'], 'data' => $data, 'author' => auth()->user()?->name,
                'updated_at' => now()->toIso8601String(),
            ]);
            CloudData::clear();
        });
    }
    public function mergeDraft(array $published, array $draft, bool $checkConflict): array
    {
        return $this->mergeMap($published, $draft['base'], $draft['data'], $checkConflict);
    }

    private function mergeMap(array $current, array $base, array $draft, bool $check, string $prefix = ''): array
    {
        $same = static fn ($a, $b) => is_array($a) && is_array($b) ? self::fingerprint($a) === self::fingerprint($b) : $a === $b;
        foreach (array_unique([...array_keys($base), ...array_keys($draft)]) as $key) {
            $beforeExists = array_key_exists($key, $base);
            $afterExists = array_key_exists($key, $draft);
            $exists = array_key_exists($key, $current);
            $before = $base[$key] ?? null;
            $after = $draft[$key] ?? null;
            $live = $current[$key] ?? null;
            if ($beforeExists === $afterExists && $same($before, $after)) continue;
            $path = $prefix.$key;
            if ($beforeExists && $afterExists && $exists && is_array($before) && is_array($after) && is_array($live)
                && !array_is_list($before) && !array_is_list($after) && !array_is_list($live)) {
                $current[$key] = $this->mergeMap($live, $before, $after, $check, $path.'.');
                continue;
            }
            // Indexed lists are replaced as a unit: never combine different people by position.
            if ($check && !($exists === $beforeExists && $same($live, $before)) && !($exists === $afterExists && $same($live, $after))) {
                throw ValidationException::withMessages(['content' => 'Bagian '.ContentLabels::label($path).' berubah di landing page. Buka editor, periksa isi terbaru, lalu simpan kembali.']);
            }
            if ($afterExists) $current[$key] = $after;
            else unset($current[$key]);
        }
        return $current;
    }
    public function publish(): int
    {
        return CloudData::transaction(function () {
            $drafts = CloudData::store()->all('dashboard_content_drafts');
            foreach ($drafts as $draft) {
                $published = CloudData::store()->get('content', $draft['id']);
                if (!$published) throw ValidationException::withMessages(['content' => 'Bagian website sudah dihapus. Muat ulang editor.']);
                $merged = $this->mergeDraft($this->clean($published), $draft, true);
                if ($draft['id'] === 'teachers') TeacherDirectory::validate(TeacherDirectory::withDefaults($merged));
                CloudData::store()->put('content', $draft['id'], $merged);
                CloudData::store()->delete('dashboard_content_drafts', $draft['id']);
            }
            // Invalidate the existing landing-page cache in the same commit.
            (new FirestoreCacheStore(CloudData::store(), config('school.landing_cache_prefix')))->forget('school-content-firestore');
            CloudData::clear();
            return count($drafts);
        });
    }
    public static function image(string $path): string
    {
        return preg_match('#^/(?:assets/(?:design|team)|media)/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$#', $path) ? $path : '';
    }

    public static function imageUrl(string $path): string
    {
        $safe = self::image($path);
        return $safe === '' ? '' : (str_starts_with($safe, '/assets/') ? rtrim(config('school.landing_url'), '/').$safe : $safe);
    }
}
