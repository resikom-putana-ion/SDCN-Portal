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
        $special = ['periode' => ['Periode & Kuota PPDB', 'admissions'], 'pembayaran' => ['Pembayaran', 'finance'], 'website' => ['Kelola Website Sekolah', 'website'], 'halaman' => ['Halaman & Bagian', 'website'], 'media' => ['Media Website', 'website'], 'publikasi' => ['Tinjauan & Publikasi', 'website'], 'pengaturan' => ['Pengaturan Sekolah', 'settings'], 'pengguna' => ['Pengguna & Izin', 'settings'], 'laporan' => ['Laporan Sekolah', 'reports'], 'bahasa' => ['Bahasa Dashboard', ''], 'notifikasi' => ['Notifikasi', ''], 'audit' => ['Audit Admin · Usulan penyederhanaan', 'settings'], 'kenaikan' => ['Kenaikan Kelas & Kelulusan', 'school'], 'aktivasi' => ['Siswa Berhasil Diaktifkan', 'admissions'], 'cari' => ['Hasil Pencarian', ''], 'terbit' => ['Perubahan Website Diterbitkan', 'website']];
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
                $rules[$name] = ['required', 'string', function ($attribute, $value, $fail) { if (!SchoolRecord::ofKind('students')->find($value)) $fail('Siswa tidak ditemukan.'); }];
            }
        }
        if ($kind === 'grades') {
            $rules['score'] = 'required|integer|min:0|max:100';
        }
        $data = $request->validate($rules);
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
            'news' => 'Draf','students','staff','classes' => 'Aktif','announcements' => 'Terbit','transactions' => 'Selesai',default => $data['status'] ?? $record?->value('status', '') ?? ''
        };
        $data = [...($record?->data ?? []), ...$data, 'author' => auth()->user()->name, 'status' => $status];
        $code = $data['nis'] ?? $data['employee_id'] ?? $record?->code ?? strtoupper($kind).'-'.Str::uuid();
        $record ??= new SchoolRecord(['kind' => $kind]);
        DB::transaction(function () use ($record, $code, $data, $kind) {
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

        return redirect(in_array($kind, ['students', 'staff']) ? '/admin/'.$screen.'/'.$record->id : '/admin/'.$screen)->with('success', 'Perubahan berhasil disimpan.');
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
