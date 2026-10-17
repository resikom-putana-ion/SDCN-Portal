<?php

namespace App\Http\Controllers;

use App\Models\SchoolRecord;
use App\Support\School;
use Illuminate\Http\Request;
use App\Support\CloudData as DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['title' => 'Beranda', 'group' => '', 'screen' => '']);
    }

    public function page(Request $request, string $screen, ?string $id = null, ?string $tab = null)
    {
        if ($screen === 'berita') return redirect('/admin/website/gallery/edit');
        $cfg = School::config();
        $definition = $cfg['pages'][$screen] ?? null;
        $shared = ['screen' => $screen, 'tab' => $tab ?? 'detail'];
        if ($definition) {
            [$title,$group,$kind,$columns,$create] = $definition;
            $shared += compact('title', 'group', 'kind', 'columns', 'create');
            $record = $id && $id !== 'baru' ? SchoolRecord::ofKind($kind)->findOrFail($id) : null;
            if ($id === 'baru' || $tab === 'edit') {
                abort_unless(isset($cfg['forms'][$kind]), 404);

                return view('admin.form', $shared + ['titleForm' => $record ? 'Edit Profil '.($kind === 'students' ? 'Siswa' : ($kind === 'staff' ? 'Guru / Staf' : $title)) : $create, 'record' => $record, 'fields' => $cfg['forms'][$kind]]);
            }
            if ($record && in_array($kind, ['students', 'staff'])) {
                $shared['title'] = $kind === 'students' ? 'Detail Siswa' : ($record->value('position') === 'Tata Usaha' ? 'Detail Staf' : 'Detail Guru');
                $shared['breadcrumb'] = 'Data sekolah / '.($kind === 'students' ? 'Siswa & keluarga' : 'Guru & staf').' / '.$record->value('name');

                return view('admin.profile', $shared + compact('record'));
            }
            if ($record && $kind === 'applicants') {
                $shared['title'] = $screen === 'daftar-ulang' ? 'Pemeriksaan Daftar Ulang' : 'Detail Pendaftar';
                $shared['breadcrumb'] = 'Penerimaan siswa / '.$record->code;

                return view($screen === 'daftar-ulang' ? 'admin.offline' : 'admin.applicant', $shared + compact('record'));
            }
            if ($record && isset($cfg['forms'][$kind])) {
                return view('admin.form', $shared + ['titleForm' => 'Edit '.$title, 'record' => $record, 'fields' => $cfg['forms'][$kind]]);
            }
            $records = $this->records($kind, $screen, $request);

            return view('admin.list', $shared + compact('records'));
        }
        $special = ['periode' => ['Periode & Kuota PPDB', 'admissions'], 'pembayaran' => ['Pembayaran', 'finance'], 'website' => ['Kelola Website Sekolah', 'website'], 'halaman' => ['Halaman & Bagian', 'website'], 'media' => ['Media Website', 'website'], 'publikasi' => ['Tinjauan & Publikasi', 'website'], 'pengaturan' => ['Pengaturan Sekolah', 'settings'], 'laporan' => ['Laporan Sekolah', 'reports'], 'bahasa' => ['Bahasa Dashboard', ''], 'notifikasi' => ['Notifikasi', ''], 'audit' => ['Audit Admin · Usulan penyederhanaan', 'settings'], 'kenaikan' => ['Kenaikan Kelas & Kelulusan', 'school'], 'aktivasi' => ['Siswa Berhasil Diaktifkan', 'admissions'], 'cari' => ['Hasil Pencarian', ''], 'terbit' => ['Perubahan Website Diterbitkan', 'website']];
        abort_unless(isset($special[$screen]), 404);
        [$title,$group] = $special[$screen];
        $shared += compact('title', 'group', 'id');
        if (in_array($screen, ['periode', 'pengaturan'])) {
            $kind = $screen === 'periode' ? 'period' : 'settings';
            $record = SchoolRecord::ofKind($kind)->firstOrFail();

            return view('admin.form', $shared + ['kind' => $kind, 'record' => $record, 'fields' => $cfg['forms'][$kind], 'titleForm' => $title]);
        }
        if ($screen === 'pembayaran') {
            return view('admin.payments', $shared);
        }
        if (in_array($screen, ['website', 'halaman', 'media', 'publikasi', 'terbit'])) {
            return view('website.manage', $shared);
        }

        return view('admin.special', $shared);
    }

    public function records(string $kind, string $screen, Request $request)
    {
        $records = SchoolRecord::ofKind($kind)->get();
        if ($screen === 'daftar-ulang') {
            $records = $records->filter(fn ($r) => in_array($r->value('status'), ['Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan', 'Aktif']));
        }

        return $records->filter(function ($r) use ($request, $kind) {
            $data = $kind === 'invoices' ? School::invoice($r) : $r->data;
            $text = mb_strtolower($r->code.' '.implode(' ', array_filter($data, 'is_scalar')));
            $year = $r->value('year');
            if (! $year && $r->value('student_id')) {
                $year = SchoolRecord::ofKind('students')->find($r->value('student_id'))?->value('year');
            }
            $year ??= SchoolRecord::ofKind('settings')->first()?->value('year', '2026/2027');

            return (! $request->filled('q') || str_contains($text, mb_strtolower($request->string('q'))))
                && (! $request->filled('filter') || in_array($request->input('filter'), $data, true))
                && (! $request->filled('year') || $request->input('year') === $year);
        })->values();
    }

    public function save(Request $request, string $kind, ?string $id = null)
    {
        $cfg = School::config();
        abort_unless(isset($cfg['forms'][$kind]), 404);
        $record = $id ? SchoolRecord::ofKind($kind)->findOrFail($id) : null;
        abort_if($record?->value('student_record_archived'), 409, 'Arsip siswa tidak dapat diubah. Buat koreksi melalui catatan audit sekolah.');
        $rules = [];
        foreach ($cfg['forms'][$kind] as $field) {
            $name = $field['name'];
            $type = $field['type'];
            $rules[$name] = in_array($name, ['notes', 'father', 'body', 'summary', 'previous_school']) ? 'nullable' : 'required';
            $rules[$name] .= match ($type) {
                'email' => '|email|max:190','number' => '|integer|min:0|max:1000000000','date' => '|date','time' => '|date_format:H:i',default => '|string|max:'.($type === 'textarea' ? '10000' : '1000')
            };
            if ($type === 'select') {
                $rules[$name] .= '|in:'.implode(',', $field['options']);
            }
            if ($type === 'student') {
                $rules[$name] = ['required', 'string', function ($attribute, $value, $fail) use ($record) {
                    $student = SchoolRecord::ofKind('students')->find($value);
                    if (!$student) $fail('Siswa tidak ditemukan.');
                    elseif ($student->value('deletion_plan')) $fail('Siswa sedang dihapus.');
                    elseif ($student->value('status') !== 'Aktif' && (string) $record?->value('student_id') !== $value) $fail('Pilih siswa aktif untuk pencatatan baru.');
                }];
            }
        }
        if ($kind === 'grades') {
            $rules['score'] = 'required|integer|min:0|max:100';
        }
        if (in_array($kind, ['students', 'staff'])) {
            $rules['name'] = ['required', 'string', 'max:1000', function ($attribute, $value, $fail) {
                if ($value !== mb_strtoupper($value)) $fail('Nama harus menggunakan huruf kapital, contoh: BUDI SANTOSO.');
            }];
            $rules['photo_crop'] = ['nullable', 'string', 'max:700000', function ($attribute, $value, $fail) {
                $bytes = str_starts_with($value, 'data:image/jpeg;base64,') ? base64_decode(substr($value, 23), true) : false;
                $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                if (!$size || $size[0] !== 512 || $size[1] !== 512 || $size[2] !== IMAGETYPE_JPEG) $fail('Foto harus dipotong melalui alat crop menjadi gambar persegi.');
            }];
        }
        $data = $request->validate($rules);
        $photo = $data['photo_crop'] ?? null;
        unset($data['photo_crop']);
        if (isset($data['student_id'])) {
            $student = SchoolRecord::ofKind('students')->findOrFail($data['student_id']);
            $data['name'] = $kind === 'invoices' ? $data['name'].' '.$data['period'].' · '.$student->value('name') : $student->value('name');
            $data['class'] = $student->value('class');
        }
        if (in_array($kind, ['students', 'staff'])) {
            $key = $kind === 'students' ? 'nis' : 'employee_id';
            if (SchoolRecord::where('code', $data[$key])->get()->first(fn ($row) => (string) $row->id !== (string) $record?->id)) throw \Illuminate\Validation\ValidationException::withMessages([$key => 'Nomor sudah digunakan.']);
        }
        if ($kind === 'invoices' && $record && DB::table('school_payments')->where('invoice_id', $record->id)->exists()) {
            return back()->withErrors(['amount' => 'Tagihan dengan pembayaran tidak dapat diubah.']);
        }
        $status = match ($kind) {
            'students' => $record?->value('status', 'Aktif') ?? 'Aktif',
            'news' => 'Draf','staff','classes' => 'Aktif','announcements' => 'Terbit','transactions' => 'Selesai',default => $data['status'] ?? $record?->value('status', '') ?? ''
        };
        $data = [...($record?->data ?? []), ...$data, 'author' => auth()->user()->name, 'status' => $status];
        $code = $data['nis'] ?? $data['employee_id'] ?? $record?->code ?? strtoupper($kind).'-'.Str::uuid();
        $record ??= new SchoolRecord(['kind' => $kind]);
        DB::transaction(function () use ($record, $code, $data, $kind, $photo) {
            $fresh = null;
            if ($record->id) {
                $fresh = SchoolRecord::ofKind($kind)->findOrFail($record->id);
                abort_if($fresh->value('deletion_plan'), 409, 'Penghapusan siswa sedang berlangsung.');
                if ($kind === 'students') {
                    foreach (['status', 'status_changed_at', 'status_changed_by'] as $key) {
                        if (array_key_exists($key, $fresh->data)) $data[$key] = $fresh->data[$key];
                    }
                }
            }
            if (isset($data['student_id'])) {
                $student = SchoolRecord::ofKind('students')->findOrFail($data['student_id']);
                abort_if($student->value('deletion_plan'), 409, 'Penghapusan siswa sedang berlangsung.');
                if ($student->value('status') !== 'Aktif' && (string) ($fresh?->value('student_id') ?? '') !== (string) $data['student_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['student_id' => 'Pilih siswa aktif untuk pencatatan baru.']);
                }
            }
            if ($photo) {
                $path = ($kind === 'staff' ? 'staff-photos/' : 'student-photos/').Str::uuid().'.jpg';
                app(\App\Services\FirestoreFileStorage::class)->put($path, base64_decode(substr($photo, 23), true), 'image/jpeg');
                $data['photo_path'] = $path;
            }
            $record->fill(['code' => $code, 'data' => $data])->save();
            if (in_array($kind, ['settings', 'period'])) {
                $website = app(\App\Services\WebsiteContent::class);
                $editor = $website->editor('settings');
                $draft = $editor['data'];
                $mapping = $kind === 'period' ? ['period' => 'period', 'age' => 'age', 'fee' => 'fee'] : ['email' => 'email', 'phone' => 'whatsapp', 'hours' => 'hours', 'address' => 'address', 'founded' => 'founded', 'accreditation' => 'accreditation', 'name' => 'school_name'];
                foreach ($mapping as $from => $to) if (isset($data[$from])) $draft[$to] = $from === 'fee' ? School::money($data[$from]) : (string) $data[$from];
                $website->save('settings', $draft, $editor['version']);
            }
            School::log('Menyimpan '.($data['name'] ?? $kind), $kind, 'Tersimpan');
        });
        $screen = collect($cfg['pages'])->search(fn ($p) => $p[2] === $kind) ?: ($kind === 'period' ? 'periode' : 'pengaturan');

        return redirect(in_array($kind, ['students', 'staff']) ? '/admin/'.$screen.'/'.$record->id : '/admin/'.$screen.($kind === 'settings' ? '?tab=sekolah' : ''))->with('success', 'Perubahan berhasil disimpan.');
    }

    public function studentPhoto(SchoolRecord $record)
    {
        return $this->profilePhoto($record, 'students');
    }

    public function staffPhoto(SchoolRecord $record)
    {
        return $this->profilePhoto($record, 'staff');
    }

    private function profilePhoto(SchoolRecord $record, string $kind)
    {
        abort_unless($record->kind === $kind && $record->value('photo_path'), 404);
        $photo = app(\App\Services\FirestoreFileStorage::class)->get($record->value('photo_path'));
        abort_unless($photo && $photo['content_type'] === 'image/jpeg', 404);
        return response($photo['contents'])->header('Content-Type', 'image/jpeg')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function export(Request $request, string $kind)
    {
        $page = collect(School::config()['pages'])->first(fn ($p) => $p[2] === $kind);
        abort_unless($page, 404);
        $columns = $page[3];
        $records = $this->records($kind, '', $request);

        return response()->streamDownload(function () use ($records, $columns, $kind) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, array_values($columns));
            foreach ($records as $record) {
                $data = $kind === 'invoices' ? School::invoice($record) : $record->data;
                fputcsv($file, array_map(function ($key) use ($data) {
                    $value = (string) ($data[$key] ?? '');

                    return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                }, array_keys($columns)));
            } fclose($file);
        }, $kind.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
