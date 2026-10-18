<?php

namespace App\Services;

use App\Support\CloudData;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WebsiteContent
{
    public const HOME_DEFAULTS = ['campus_image'=>'/assets/design/sd-ceria-nusantara-campus.png'];
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
        if (isset($all['home'])) $all['home'] = $this->displayData('home', $all['home']);
        return $all;
    }
    public function editor(string $section): array
    {
        $published = CloudData::store()->get('content', $section);
        abort_unless($published, 404, 'Bagian website belum tersedia di Firestore.');
        $published = $this->clean($published);
        $draft = CloudData::store()->get('dashboard_content_drafts', $section);
        $data = $draft ? $this->mergeDraft($published, $draft, false) : $published;
        $data = $this->displayData($section, $data);
        return ['published' => $published, 'data' => $data,
            'version' => self::fingerprint([$published, $draft]), 'draft' => $draft];
    }
    public function save(string $section, array $data, string $version): void
    {
        $data = $this->displayData($section, $data);
        if ($section === 'teachers') TeacherDirectory::validate($data);
        CloudData::transaction(function () use ($section, $data, $version) {
            $current = $this->editor($section);
            if (!hash_equals($current['version'], $version)) throw ValidationException::withMessages(['content' => 'Konten berubah sejak formulir dibuka. Muat ulang halaman sebelum menyimpan.']);
            if (self::fingerprint($this->displayData($section, $current['published'])) === self::fingerprint($data)) {
                CloudData::store()->delete('dashboard_content_drafts', $section);
                CloudData::clear();
                return;
            }
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

    private function displayData(string $section, array $data): array
    {
        if ($section === 'teachers') return TeacherDirectory::withDefaults($data);
        if ($section === 'home') unset($data['principal_name']);
        return $section === 'home' ? $data + self::HOME_DEFAULTS : $data;
    }

    /** The review and the publish action must refer to the same saved documents. */
    public function review(): array
    {
        $drafts = collect(CloudData::collection('dashboard_content_drafts'))->keyBy('id')->all();
        $published = collect(CloudData::collection('content'))->keyBy('id')->all();
        $pages = [];
        $versions = [];
        foreach ($drafts as $section => $draft) {
            $live = isset($published[$section]) ? $this->clean($published[$section]) : null;
            $versions[$section] = [$live, $draft];
            $conflict = null;
            $changes = [];
            if ($live === null || !isset(self::SECTIONS[$section])) {
                $conflict = 'Halaman ini sudah tidak tersedia. Batalkan draf sebelum melanjutkan.';
            } else {
                try { $this->mergeDraft($live, $draft, true); }
                catch (ValidationException $e) { $conflict = collect($e->errors())->flatten()->first(); }
                $merged = $this->mergeDraft($live, $draft, false);
                $changes = $this->changes($this->displayData($section, $live), $this->displayData($section, $merged));
            }
            $pages[] = [
                'section' => $section, 'name' => self::SECTIONS[$section] ?? $section,
                'author' => $draft['author'] ?? 'Pengelola', 'updated_at' => $draft['updated_at'] ?? null,
                'changes' => $changes, 'conflict' => $conflict,
                'version' => self::fingerprint([$live, $draft]),
            ];
        }
        return ['pages' => $pages, 'version' => self::fingerprint($versions),
            'change_count' => array_sum(array_map(fn ($page) => count($page['changes']), $pages)),
            'has_conflicts' => collect($pages)->contains(fn ($page) => $page['conflict'] !== null)];
    }

    private function changes(array $before, array $after, string $prefix = ''): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            $path = $prefix.$key;
            if (is_array($old) || is_array($new)) {
                $changes = [...$changes, ...$this->changes(is_array($old) ? $old : [], is_array($new) ? $new : [], $path.'.')];
            } elseif ($old !== $new) {
                $changes[] = ['path' => $path, 'label' => ContentLabels::label($path), 'before' => $old, 'after' => $new,
                    'image' => (bool) preg_match('/(^image$|_image$|^logo$|\.photo$)/', $path)];
            }
        }
        return $changes;
    }

    public function discard(string $section, string $version): void
    {
        CloudData::transaction(function () use ($section, $version) {
            $editor = $this->editor($section);
            if (!hash_equals($editor['version'], $version)) throw ValidationException::withMessages(['content' => 'Draf sudah berubah. Muat ulang tinjauan sebelum membatalkannya.']);
            CloudData::store()->delete('dashboard_content_drafts', $section);
        });
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
    public function publish(?string $reviewVersion = null): int
    {
        return CloudData::transaction(function () use ($reviewVersion) {
            $review = $this->review();
            if (!$review['pages'] || $review['change_count'] === 0) throw ValidationException::withMessages(['content' => 'Belum ada perubahan tersimpan untuk diterbitkan. Edit halaman dan simpan draf terlebih dahulu.']);
            if ($reviewVersion !== null && !hash_equals($review['version'], $reviewVersion)) {
                throw ValidationException::withMessages(['content' => 'Draf atau konten terbit berubah sejak tinjauan dibuka. Periksa kembali perubahan terbaru sebelum menerbitkan.']);
            }
            $drafts = CloudData::store()->all('dashboard_content_drafts');
            foreach ($drafts as $draft) {
                $published = CloudData::store()->get('content', $draft['id']);
                if (!$published) throw ValidationException::withMessages(['content' => 'Bagian website sudah dihapus. Muat ulang editor.']);
                $merged = $this->displayData($draft['id'], $this->mergeDraft($this->clean($published), $draft, true));
                if ($draft['id'] === 'teachers') TeacherDirectory::validate(TeacherDirectory::withDefaults($merged));
                CloudData::store()->put('content', $draft['id'], $merged);
                CloudData::store()->delete('dashboard_content_drafts', $draft['id']);
            }
            // Invalidate the existing landing-page cache in the same commit.
            (new FirestoreCacheStore(CloudData::store(), config('school.landing_cache_prefix')))->forget('school-content-firestore');
            CloudData::store()->put('dashboard_runtime', 'website_publication', [
                'pages' => array_column($review['pages'], 'name'), 'change_count' => $review['change_count'],
                'author' => auth()->user()?->name, 'published_at' => now()->toIso8601String(),
            ]);
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
