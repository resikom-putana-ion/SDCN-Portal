<?php

namespace App\Http\Controllers;

use App\Services\FirestoreFileStorage;
use App\Support\CloudData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminProfileController extends Controller
{
    public function save(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'photo_crop' => ['nullable', 'string', 'max:700000', function ($attribute, $value, $fail) {
                $bytes = str_starts_with($value, 'data:image/jpeg;base64,') ? base64_decode(substr($value, 23), true) : false;
                $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                if (!$size || $size[0] !== 512 || $size[1] !== 512 || $size[2] !== IMAGETYPE_JPEG) {
                    $fail('Foto harus dipotong melalui alat crop menjadi gambar persegi.');
                }
            }],
        ]);
        $name = trim($data['name']);
        if ($name === '') return back()->withErrors(['name' => 'Nama admin wajib diisi.'])->withInput();
        $path = null;
        if (!empty($data['photo_crop'])) {
            $path = 'admin-photos/'.Str::uuid().'.jpg';
            app(FirestoreFileStorage::class)->put($path, base64_decode(substr($data['photo_crop'], 23), true), 'image/jpeg');
        }
        CloudData::store()->transaction(function () use ($request, $name, $path) {
            $id = (string) $request->user()->getAuthIdentifier();
            $row = CloudData::store()->get('admins', $id);
            abort_unless($row, 404);
            $row['name'] = $name;
            if ($path) $row['photo_path'] = $path;
            $row['updated_at'] = now()->toIso8601String();
            CloudData::store()->put('admins', $id, $row);
        });
        return redirect('/admin/pengaturan')->with('success', 'Profil admin berhasil disimpan.');
    }

    public function photo(Request $request)
    {
        $path = $request->user()->photo_path ?? null;
        abort_unless($path, 404);
        $photo = app(FirestoreFileStorage::class)->get($path);
        abort_unless($photo && $photo['content_type'] === 'image/jpeg', 404);
        return response($photo['contents'])->header('Content-Type', 'image/jpeg')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
