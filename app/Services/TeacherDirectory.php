<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// Shared content contract: keep this class and the fallback resource in both projects.
class TeacherDirectory
{
    public static function defaults(): array
    {
        return (require resource_path('content/staff-directory.php')) + [
            'directory_eyebrow' => 'KENALI KELUARGA SEKOLAH KAMI',
            'directory_title' => "Wajah ramah,\nteman tumbuh anak.",
            'directory_description' => 'Di kelas, di perpustakaan, hingga di gerbang sekolah—ada tim yang siap mendampingi langkah kecil buah hati Anda.',
            'demo_profiles' => true,
        ];
    }

    public static function withDefaults(array $saved): array
    {
        $data = $saved + self::defaults();
        $data['people'] = array_map(fn (array $person) => $person + ['photo'=>null], $data['people']);
        return $data;
    }

    public static function validate(array $data): void
    {
        $indices = array_keys($data['people'] ?? []);
        Validator::make($data, [
            'people' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'people.*.name' => 'required|string|max:150',
            'people.*.role' => 'required|string|max:150',
            'people.*.description' => 'required|string|max:2000',
            'people.*.group' => ['required', Rule::in(['pimpinan', 'guru', 'staf'])],
            'people.*.portrait' => 'required|integer|min:0|max:29',
            'people.*.photo' => ['nullable', 'string', 'regex:#^/(?:assets/(?:design|team)|media)/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)$#'],
            'subjects' => 'present|array|list|max:100',
            'subjects.*.title' => 'required|string|max:150',
            'subjects.*.by' => 'required|string|max:300',
            'subjects.*.text' => 'required|string|max:2000',
            'structure.head' => ['required', 'integer', Rule::in($indices)],
            'structure.branches' => 'present|array|list|max:30',
            'structure.branches.*.title' => 'required|string|max:150',
            'structure.branches.*.summary' => 'required|string|max:2000',
            'structure.branches.*.tone' => ['required', Rule::in(['blue', 'green', 'yellow'])],
            'structure.branches.*.lead' => ['required', 'integer', Rule::in($indices)],
            'structure.branches.*.teams' => 'present|array|list|max:100',
            'structure.branches.*.teams.*.title' => 'required|string|max:150',
            'structure.branches.*.teams.*.members' => 'present|array|list|max:100',
            'structure.branches.*.teams.*.members.*' => ['required', 'integer', Rule::in($indices)],
            'directory_eyebrow' => 'required|string|max:150',
            'directory_title' => 'required|string|max:300',
            'directory_description' => 'required|string|max:2000',
            'demo_profiles' => 'required|boolean',
        ], ['in' => 'Pilihan :attribute tidak tersedia. Pilih anggota atau kategori yang ada.'])->validate();
    }
}
