<?php

namespace App\Http\Controllers;

use App\Models\SchoolRecord;
use App\Support\School;
use Illuminate\Http\Request;
use App\Support\CloudData as DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteController extends Controller
{
    public function preview()
    {
        return view('website.preview', ['title' => 'Pratinjau Landing Page', 'group' => 'website', 'screen' => 'website', 'content' => School::content(true), 'period' => School::period(), 'preview' => true]);
    }

    public function edit(string $section, \App\Services\WebsiteContent $content)
    {
        $section = $content->section($section);
        $editor = $content->editor($section);
        return view('website.edit', [
            'title' => 'Edit '.\App\Services\WebsiteContent::SECTIONS[$section], 'group' => 'website', 'screen' => 'halaman',
            'section' => $section, 'fields' => \Illuminate\Support\Arr::dot($editor['data']), 'version' => $editor['version'],
        ]);
    }

    public function save(Request $request, string $section, \App\Services\WebsiteContent $content, \App\Services\FirestoreFileStorage $files)
    {
        $section = $content->section($section);
        $request->validate(['values' => 'required|array', 'version' => 'required|string|size:64', 'uploads.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:1024']);
        DB::transaction(function () use ($request, $section, $content, $files) {
            $editor = $content->editor($section);
            $data = $editor['data'];
            $fields = \Illuminate\Support\Arr::dot($data);
            $values = $request->input('values');
            foreach ($fields as $key => $value) {
                if (is_array($value)) continue;
                $new = $values[$key] ?? $value;
                \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'nullable|string|max:10000'])->validate();
                $new ??= '';
                if (preg_match('/(^image$|_image$|^logo$|\.photo$)/', $key)) {
                    if (\App\Services\WebsiteContent::image($new) === '') throw \Illuminate\Validation\ValidationException::withMessages(['values' => 'Gunakan gambar website yang tersedia atau unggah gambar baru.']);
                    if ($file = $request->file('uploads.'.array_search($key, array_keys($fields)))) {
                        $path = 'media/'.Str::uuid().'.'.$file->guessExtension();
                        $files->putUploadedFile($path, $file);
                        $new = '/'.$path;
                    }
                }
                if ($key === 'email') \Illuminate\Support\Facades\Validator::make(['email' => $new], ['email' => 'required|email'])->validate();
                if ($key === 'whatsapp') \Illuminate\Support\Facades\Validator::make(['whatsapp' => $new], ['whatsapp' => ['required', 'regex:/^\+?[0-9 ()-]{9,25}$/']])->validate();
                if (is_bool($value)) $new = filter_var($new, FILTER_VALIDATE_BOOLEAN);
                elseif (is_int($value)) { \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'integer'])->validate(); $new = (int) $new; }
                elseif (is_float($value)) { \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'numeric'])->validate(); $new = (float) $new; }
                \Illuminate\Support\Arr::set($data, $key, $new);
            }
            $content->save($section, $data, $request->input('version'));
            School::log('Menyimpan draf '.\App\Services\WebsiteContent::SECTIONS[$section], 'Website', 'Draf');
        });
        return back()->with('success', 'Draf tersimpan. Tinjau dan terbitkan agar tampil di landing page.');
    }

    public function publish(Request $request, \App\Services\WebsiteContent $content)
    {
        $request->validate(['checks' => 'required|array|size:5', 'checks.*' => 'required|distinct|in:text,image,contact,period,offline', 'notes' => 'required|string|max:3000']);
        DB::transaction(function () use ($request, $content) {
            $content->publish();
            School::log('Menerbitkan website: '.$request->notes, 'Website', 'Terbit');
        });
        return redirect('/admin/terbit')->with('success', 'Konten landing page berhasil diperbarui.');
    }

    public function upload(Request $request, \App\Services\FirestoreFileStorage $files)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:1024', 'alt' => 'required|string|max:300']);
        $file = $request->file('image');
        $path = 'media/'.Str::uuid().'.'.$file->guessExtension();
        DB::transaction(function () use ($files, $path, $file, $request) {
            $files->putUploadedFile($path, $file);
            SchoolRecord::create(['kind' => 'media', 'code' => 'MEDIA-'.Str::uuid(), 'data' => ['name' => $file->getClientOriginalName(), 'image' => '/'.$path, 'alt' => $request->alt]]);
        });
        return back()->with('success', 'Media tersimpan di Firestore.');
    }

    public function media(string $filename, \App\Services\FirestoreFileStorage $files)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_.-]+\.(png|jpe?g|webp)$/', $filename), 404);
        $file = $files->get('media/'.$filename);
        abort_unless($file && in_array($file['content_type'], ['image/png', 'image/jpeg', 'image/webp'], true), 404);
        return response($file['contents'])->header('Content-Type', $file['content_type'])->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'public, max-age=300');
    }

    public function application()
    {
        return view('public.application', ['period' => School::period()]);
    }

    public function submitApplication(Request $request, \App\Services\FirestoreFileStorage $files)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'birth_date' => 'required|date|before:today', 'guardian' => 'required|string|max:150', 'relationship' => 'required|in:Ibu,Ayah,Wali', 'phone' => 'required|string|max:30', 'email' => 'required|email|max:190', 'gender' => 'required|in:Perempuan,Laki-laki', 'consent' => 'accepted', 'birth_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:1024', 'family_card' => 'required|file|mimes:pdf,jpg,jpeg,png|max:1024', 'photo' => 'required|file|mimes:jpg,jpeg,png|max:1024']);
        $number = DB::transaction(function () use ($request, $data, $files) {
            $period = School::period();
            $number = 'PPDB-'.substr($period['year'], 0, 4).'-'.strtoupper(Str::random(10));
            $record = SchoolRecord::create(['id' => $number, 'kind' => 'applicants', 'code' => $number, 'data' => [...collect($data)->except(['consent', 'birth_certificate', 'family_card', 'photo'])->all(), 'number' => $number, 'year' => $period['year'], 'status' => 'Pemeriksaan', 'payment' => 'Belum dicatat', 'checks' => [], 'paid' => false, 'notes' => '']]);
            $documents = [];
            foreach (['birth_certificate' => 'Akta kelahiran', 'family_card' => 'Kartu Keluarga', 'photo' => 'Pasfoto'] as $key => $label) {
                $file = $request->file($key);
                $path = 'applications/'.$number.'/'.Str::uuid().'.'.$file->extension();
                $files->putUploadedFile($path, $file);
                $documents[$key] = ['name' => $label.'.'.$file->extension(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
            }
            $application = DB::store()->get('applications', $number);
            DB::store()->put('applications', $number, [...$application, 'documents' => $documents, 'consent_at' => now()->toIso8601String()]);
            School::log('Pendaftaran baru '.$number, 'PPDB', 'Pemeriksaan');
            return $number;
        });
        return redirect('/ppdb/berhasil')->with('registration', $number);
    }
}
