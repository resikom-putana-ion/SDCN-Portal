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
        return view('website.preview', ['title' => 'Pratinjau Website Sekolah', 'group' => 'website', 'screen' => 'website']);
    }

    public function frame(Request $request)
    {
        return response(\App\Services\LandingPreview::render(School::content($request->query('state') !== 'published'), (string) $request->query('page', 'home'), $request->boolean('edit'))->render())
            ->header('Cache-Control', 'private, no-store')
            ->header('Content-Security-Policy', "form-action 'none'; frame-ancestors 'self'; base-uri 'none'");
    }

    public function previewChanges(Request $request, string $section, \App\Services\WebsiteContent $content, \App\Services\FirestoreFileStorage $files)
    {
        $section = $content->section($section);
        $data = $this->candidate($request, $section, $content, $files, false);
        $site = $content->all(true);
        $site[$section] = $data;
        $page = (string) $request->query('page', $section === 'settings' ? 'home' : $section);
        return response(\App\Services\LandingPreview::render($site, $page, true)->render())->header('Cache-Control', 'private, no-store');
    }

    public function edit(string $section, \App\Services\WebsiteContent $content)
    {
        $section = $content->section($section);
        $editor = $content->editor($section);
        return view('website.edit', [
            'title' => 'Edit '.\App\Services\WebsiteContent::SECTIONS[$section], 'group' => 'website', 'screen' => 'halaman',
            'section' => $section, 'fields' => \Illuminate\Support\Arr::dot($editor['data']), 'version' => $editor['version'],
            'data' => $editor['data'], 'blocks' => \App\Services\WebsiteEditor::blocks($section, $editor['data']),
            'draft' => $editor['draft'],
        ]);
    }

    public function save(Request $request, string $section, \App\Services\WebsiteContent $content, \App\Services\FirestoreFileStorage $files)
    {
        $section = $content->section($section);
        $request->validate(['intent' => 'nullable|in:save,review', 'block' => 'nullable|string|max:80']);
        DB::transaction(function () use ($request, $section, $content, $files) {
            $data = $this->candidate($request, $section, $content, $files, true);
            $content->save($section, $data, $request->input('version'));
            School::log('Menyimpan draf '.\App\Services\WebsiteContent::SECTIONS[$section], 'Website', 'Draf');
        });
        $editor = $content->editor($section);
        $message = $editor['draft'] ? 'Draf tersimpan. Perubahan siap ditinjau dan diterbitkan.' : 'Semua isian sama dengan konten yang sudah terbit.';
        $editUrl = '/admin/website/'.$section.'/edit'.($request->filled('block') ? '?'.http_build_query(['block'=>$request->input('block')]) : '');
        if ($request->expectsJson()) return response()->json([
            'version'=>$editor['version'], 'has_draft'=>(bool) $editor['draft'], 'message'=>$message,
            'saved_at'=>now()->format('H:i'), 'reload_url'=>$request->hasFile('uploads') ? $editUrl : null,
        ]);
        return redirect($request->input('intent') === 'review' ? '/admin/publikasi' : $editUrl)->with('success', $message);
    }

    private function candidate(Request $request, string $section, \App\Services\WebsiteContent $content, \App\Services\FirestoreFileStorage $files, bool $persist): array
    {
        $request->validate(['values' => 'required_without:operation|array', 'version' => 'required|string|size:64', 'operation'=>'nullable|string|max:200', 'uploads.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:1024']);
        $editor = $content->editor($section);
        if (!hash_equals($editor['version'], $request->input('version'))) throw \Illuminate\Validation\ValidationException::withMessages(['content'=>'Konten berubah sejak formulir dibuka. Muat ulang halaman sebelum menyimpan.']);
        $data = $editor['data'];
        $fields = \Illuminate\Support\Arr::dot($data);
        $values = $request->input('values', []);
        $previewImages = [];
        $imageKeys = array_map(fn ($key) => hash('sha256', $key), array_values(array_filter(array_keys($fields), fn ($key) => preg_match('/(^image$|_image$|^logo$|\.photo$)/', $key))));
        foreach (array_keys($request->file('uploads', [])) as $uploadKey) {
            if (!in_array((string) $uploadKey, $imageKeys, true)) throw \Illuminate\Validation\ValidationException::withMessages(['uploads'=>'Form gambar sudah berubah. Salin perubahan teks Anda, muat ulang editor, lalu pilih kembali gambarnya.']);
        }
        foreach ($fields as $key => $value) {
            if (is_array($value)) continue;
            if (str_ends_with($key, '.icon') || str_ends_with($key, '.portrait')) continue;
            $new = array_key_exists($key, $values) ? $values[$key] : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
            \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'nullable|string|max:10000'])->validate();
            $new ??= '';
            if (preg_match('/(^image$|_image$|^logo$|\.photo$)/', $key)) {
                if ($file = $request->file('uploads.'.hash('sha256', $key))) {
                    if ($persist) {
                        $path = 'media/'.Str::uuid().'.'.$file->guessExtension();
                        $files->putUploadedFile($path, $file);
                        $new = '/'.$path;
                    } else {
                        $previewImages[$key] = 'data:'.$file->getMimeType().';base64,'.base64_encode($file->get());
                    }
                }
                if (!(preg_match('/^people\.\d+\.photo$/', $key) && $new === '') && \App\Services\WebsiteContent::image($new) === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['values' => 'Gunakan gambar website yang tersedia atau unggah gambar baru.']);
                }
            }
            if ($key === 'email') \Illuminate\Support\Facades\Validator::make(['email' => $new], ['email' => 'required|email'])->validate();
            if ($key === 'whatsapp') \Illuminate\Support\Facades\Validator::make(['whatsapp' => $new], ['whatsapp' => ['required', 'regex:/^\+?[0-9 ()-]{9,25}$/']])->validate();
            if ($key === 'map_url') \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => ['required', 'regex:#^https://(?:www\.)?google\.com/maps/#i']])->validate();
            if (is_bool($value)) { \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'boolean'])->validate(); $new = (bool) $new; }
            elseif (is_int($value)) { \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'integer'])->validate(); $new = (int) $new; }
            elseif (is_float($value)) { \Illuminate\Support\Facades\Validator::make(['value' => $new], ['value' => 'numeric'])->validate(); $new = (float) $new; }
            elseif ($value === null && $new === '') $new = null;
            \Illuminate\Support\Arr::set($data, $key, $new);
        }
        if ($section === 'teachers') \App\Services\TeacherDirectory::validate($data);
        $data = \App\Services\WebsiteEditor::operation($data, $section, $request->input('operation'));
        if ($section === 'teachers') \App\Services\TeacherDirectory::validate($data);
        foreach ($previewImages as $key=>$url) \Illuminate\Support\Arr::set($data, $key, $url);
        return $data;
    }

    public function publish(Request $request, \App\Services\WebsiteContent $content)
    {
        $request->validate(['review_version' => 'required|string|size:64', 'confirmed' => 'accepted', 'notes' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $content) {
            $count = $content->publish($request->input('review_version'));
            School::log('Menerbitkan '.$count.' halaman website'.($request->filled('notes') ? ': '.$request->notes : ''), 'Website', 'Terbit');
        });
        return redirect('/admin/terbit')->with('success', 'Konten landing page berhasil diperbarui.');
    }

    public function discard(Request $request, string $section, \App\Services\WebsiteContent $content)
    {
        $section = $content->section($section);
        $request->validate(['version'=>'required|string|size:64']);
        DB::transaction(function () use ($content, $section, $request) {
            $content->discard($section, $request->input('version'));
            School::log('Membatalkan draf '.\App\Services\WebsiteContent::SECTIONS[$section], 'Website', 'Draf');
        });
        return redirect('/admin/publikasi')->with('success', 'Draf dibatalkan. Konten yang sudah terbit tetap digunakan.');
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
        $request->validate(['payment_method' => 'required|in:offline,transfer', 'payment_proof' => 'required_if:payment_method,transfer|prohibited_if:payment_method,offline|file|mimes:pdf,jpg,jpeg,png|max:5120']);
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
            if ($request->file('payment_proof')) {
                $file = $request->file('payment_proof');
                $path = 'applications/'.$number.'/'.Str::uuid().'.'.$file->extension();
                $files->putUploadedFile($path, $file);
                $documents['payment_proof'] = ['name' => 'Bukti transfer.'.$file->extension(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
            }
            $application = DB::store()->get('applications', $number);
            DB::store()->put('applications', $number, [...$application, 'payment_method' => $request->input('payment_method'), 'documents' => $documents, 'consent_at' => now()->toIso8601String()]);
            School::log('Pendaftaran baru '.$number, 'PPDB', 'Pemeriksaan');
            return $number;
        });
        return redirect('/ppdb/berhasil')->with('registration', $number);
    }
}
