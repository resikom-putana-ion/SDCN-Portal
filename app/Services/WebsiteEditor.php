<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WebsiteEditor
{
    /** Blocks follow the order and selectors of the public landing templates. */
    public static function blocks(string $section, array $data): array
    {
        $hero = ['Pembuka halaman', ['eyebrow','title','description','image'], '.hero'];
        $definitions = match ($section) {
            'home' => [
                'hero'=>['Pembuka beranda', ['eyebrow','title','description','campus_image','image','classroom_image'], '.hero'],
                'profile'=>['Profil sekolah', ['profile_title','profile_subtitle','profile_text','values'], '.home-profile'],
                'advantages'=>['Keunggulan sekolah', ['advantages'], '.home-advantages'],
                'programs'=>['Program unggulan', ['programs'], '.home-programs'],
                'principal'=>['Sambutan kepala sekolah', ['principal_quote'], '.home-teachers'],
                'testimonials'=>['Cerita keluarga', ['testimonials'], '.home-testimonials'],
                'faq'=>['Pertanyaan umum', ['faq'], '.home-faq'],
                'tablet'=>['Tampilan tablet', ['tablet_description','tablet'], '.tablet-stats'],
            ],
            'settings' => [
                'brand'=>['Identitas sekolah', ['school_name','tagline','logo'], '.site-header'],
                'stats'=>['Angka sekolah', ['students','teachers','founded','accreditation'], '.school-stats'],
                'images'=>['Foto fasilitas & kelas', ['facilities_image','classroom_image'], '.home-facilities'],
                'admission'=>['Informasi PPDB', ['period','age','fee'], '.home-information'],
                'contact'=>['Kontak & lokasi', ['email','whatsapp','address','hours','map_url'], '.site-footer'],
                'legal'=>['Privasi & ketentuan', ['privacy','terms'], '#main'],
            ],
            'about' => ['hero'=>$hero, 'story'=>['Cerita sekolah', ['story_title','story_subtitle','story'], '.section:nth-of-type(2)'], 'values'=>['Visi, misi & nilai', ['values'], '.section:nth-of-type(3)'], 'quote'=>['Sambutan', ['quote'], '.section:nth-of-type(4)']],
            'program' => ['hero'=>$hero, 'approach'=>['Pendekatan belajar', ['approach'], '#pendekatan'], 'curriculum'=>['Kurikulum', ['curriculum'], '.section:nth-of-type(3)'], 'activities'=>['Kegiatan anak', ['activities'], '.section:nth-of-type(4)']],
            'facilities' => ['hero'=>$hero, 'spaces'=>['Ruang sekolah', ['spaces'], '.section:nth-of-type(2)'], 'features'=>['Pengalaman anak', ['features'], '.section:nth-of-type(3)']],
            'teachers' => [
                'intro'=>['Pengantar Guru & Staf', ['directory_eyebrow','directory_title','directory_description','demo_profiles'], '.people-hero'],
                'values'=>['Nilai pendampingan', ['values'], '.people-values'],
                'people'=>['Direktori Guru & Staf', ['people'], '#direktori'],
                'subjects'=>['Mata pelajaran', ['subjects'], '#mata-pelajaran'],
                'structure'=>['Struktur organisasi', ['structure'], '#struktur'],
                'team'=>['Guru di beranda', ['featured_people'], '.team-grid'],
            ],
            'gallery' => ['hero'=>$hero, 'news'=>['Berita sekolah', ['news'], '.section:nth-of-type(2)']],
            'contact' => ['hero'=>$hero, 'visit'=>['Kunjungan sekolah', ['visit_hours'], '#kunjungan']],
            default => [],
        };
        $blocks = [];
        $used = [];
        foreach ($definitions as $id => [$label, $keys, $selector]) {
            $keys = array_values(array_filter($keys, fn ($key) => array_key_exists($key, $data)));
            if ($keys) $blocks[$id] = compact('label','keys','selector');
            $used = [...$used, ...$keys];
        }
        $hidden = match ($section) {
            'teachers' => ['eyebrow','title','description','image','directory','team'],
            'home' => ['principal_name'], default => [],
        };
        $other = array_diff(array_keys($data), $used, $hidden);
        if ($other) $blocks['other'] = ['label'=>'Informasi lainnya', 'keys'=>array_values($other), 'selector'=>'main'];

        return $blocks;
    }

    public static function editableList(string $section, string $path): bool
    {
        $lists = match ($section) {
            'home'=>['values','advantages','programs','testimonials','faq','tablet.advantages','tablet.programs','tablet.admission'],
            'about'=>['values'], 'program'=>['approach','curriculum','activities'],
            'facilities'=>['spaces','features'], 'teachers'=>['people','featured_people','values','subjects','structure.branches'],
            'gallery'=>['news'], default=>[],
        };

        return in_array($path, $lists, true) || ($section === 'teachers'
            && preg_match('/^structure\.branches\.\d+\.teams(?:\.\d+\.members)?$/D', $path));
    }

    public static function operation(array $data, string $section, ?string $operation): array
    {
        if (!$operation) return $data;
        if (!preg_match('/^(add|remove|up|down):([a-z0-9_.]+)(?::(\d+))?$/D', $operation, $match)
            || !self::editableList($section, $match[2])) self::invalid('Daftar yang dipilih tidak dapat diubah.');
        [, $action, $path] = $match;
        $items = Arr::get($data, $path);
        if (!is_array($items) || !array_is_list($items)) self::invalid('Daftar tidak tersedia. Muat ulang editor.');
        $indices = array_keys($items);
        if ($action === 'add') {
            if (count($items) >= 100) self::invalid('Maksimal 100 item pada satu daftar.');
            if ($path === 'featured_people') {
                $available = array_values(array_diff(array_keys($data['people']), $items));
                if (!$available) self::invalid('Semua anggota sudah dipilih untuk beranda.');
                $items[] = $available[0];
            } else $items[] = self::newItem($path, $items);
        } else {
            $index = isset($match[3]) ? (int) $match[3] : -1;
            if (!array_key_exists($index, $items)) self::invalid('Item tidak tersedia. Muat ulang editor.');
            if ($action === 'remove') {
                if (in_array($path, ['people','team'], true) && count($items) === 1) self::invalid('Pertahankan minimal satu anggota.');
                if ($path === 'people' && ($data['structure']['head'] === $index
                    || in_array($index, array_column($data['structure']['branches'], 'lead'), true))) {
                    self::invalid('Ganti kepala sekolah atau koordinator di Struktur organisasi sebelum menghapus anggota ini.');
                }
                array_splice($items, $index, 1);
                array_splice($indices, $index, 1);
            } else {
                $target = $index + ($action === 'up' ? -1 : 1);
                if (!array_key_exists($target, $items)) self::invalid('Item sudah berada di ujung daftar.');
                [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
                [$indices[$index], $indices[$target]] = [$indices[$target], $indices[$index]];
            }
        }
        Arr::set($data, $path, $items);
        if ($path === 'people' && $action !== 'add') {
            $mapping = array_flip($indices);
            $data['featured_people'] = array_values(array_map(fn ($old) => $mapping[$old],
                array_filter($data['featured_people'], fn ($old) => isset($mapping[$old]))));
            $data['structure']['head'] = $mapping[$data['structure']['head']];
            foreach ($data['structure']['branches'] as &$branch) {
                $branch['lead'] = $mapping[$branch['lead']];
                foreach ($branch['teams'] as &$team) {
                    $team['members'] = array_values(array_map(fn ($old) => $mapping[$old],
                        array_filter($team['members'], fn ($old) => isset($mapping[$old]))));
                }
                unset($team);
            }
            unset($branch);
        }

        return $data;
    }

    private static function newItem(string $path, array $items): mixed
    {
        if (str_ends_with($path, '.members')) return 0;
        if (str_ends_with($path, '.teams')) return ['title'=>'Tim baru', 'members'=>[]];
        return match ($path) {
            'people'=>['name'=>'Anggota baru','role'=>'Guru','group'=>'guru','description'=>'Tambahkan peran dan informasi anggota.','portrait'=>0,'photo'=>null],
            'team'=>['name'=>'Anggota baru','role'=>'Guru','photo'=>'/assets/design/teacher-1.png'],
            'subjects'=>['title'=>'Mata pelajaran baru','by'=>'Tim pengajar','text'=>'Tambahkan penjelasan mata pelajaran.'],
            'structure.branches'=>['title'=>'Bidang baru','summary'=>'Tambahkan tugas bidang.','tone'=>'blue','lead'=>0,'teams'=>[]],
            'spaces'=>'Ruang baru', 'faq'=>['question'=>'Pertanyaan baru','answer'=>'Tambahkan jawaban.'],
            'testimonials'=>['name'=>'Nama orang tua','text'=>'Tambahkan cerita keluarga.'],
            'news'=>['category'=>'KEGIATAN','title'=>'Berita baru','date'=>now()->translatedFormat('j F Y'),'text'=>'Tambahkan isi berita.'],
            default=>['title'=>'Judul baru','text'=>'Tambahkan keterangan.'] + (isset($items[0]['icon']) ? ['icon'=>$items[0]['icon']] : []),
        };
    }

    public static function teacherTeams(array $data): array
    {
        $groups = ['leaders'=>['label'=>'Pimpinan & koordinator', 'members'=>array_unique([$data['structure']['head'], ...array_column($data['structure']['branches'], 'lead')])]];
        foreach ($data['structure']['branches'] as $b => $branch) foreach ($branch['teams'] as $t => $team) {
            $groups['team-'.$b.'-'.$t] = ['label'=>$team['title'], 'members'=>$team['members']];
        }
        $assigned = [];
        foreach ($groups as &$group) {
            $group['members'] = array_values(array_filter($group['members'], function ($index) use (&$assigned, $data) {
                if (!isset($data['people'][$index]) || isset($assigned[$index])) return false;
                return $assigned[$index] = true;
            }));
        }
        unset($group);
        $groups['others'] = ['label'=>'Anggota lainnya', 'members'=>array_values(array_diff(array_keys($data['people']), array_keys($assigned)))];

        return array_filter($groups, fn ($group) => count($group['members']));
    }

    private static function invalid(string $message): never
    {
        throw ValidationException::withMessages(['content'=>$message]);
    }
}
