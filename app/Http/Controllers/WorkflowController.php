<?php

namespace App\Http\Controllers;

use App\Models\SchoolRecord;
use App\Support\PdfDocument;
use App\Support\School;
use Illuminate\Http\Request;
use App\Support\CloudData as DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkflowController extends Controller
{
    public function review(Request $request, SchoolRecord $record)
    {
        abort_unless($record->kind === 'applicants', 404);
        $data = $request->validate(['decision' => 'required|in:approve,correction', 'notes' => 'required|string|max:3000']);
        DB::transaction(function () use ($record, $data) {
            $record = SchoolRecord::lockForUpdate()->findOrFail($record->id);
            abort_if($record->value('status') === 'Aktif', 409, 'Siswa sudah aktif.');
            $record->update(['data' => [...$record->data, 'status' => $data['decision'] === 'approve' ? 'Lolos administrasi' : 'Perlu perbaikan', 'notes' => $data['notes']]]);
            School::log('Memeriksa '.$record->code, 'Penerimaan siswa', $record->value('status'));
        });

        return redirect('/admin/'.($data['decision'] === 'approve' ? 'daftar-ulang' : 'pendaftar').'/'.$record->id)->with('success', 'Hasil pemeriksaan disimpan.');
    }

    public function offline(Request $request, SchoolRecord $record)
    {
        abort_unless($record->kind === 'applicants', 404);
        $data = $request->validate(['checks' => 'nullable|array', 'checks.*' => 'in:family,birth,family_card,photo', 'paid' => 'nullable|boolean', 'amount' => 'required|integer|min:0', 'method' => 'required|in:Tunai di sekolah,Transfer di sekolah', 'class' => 'required|in:1A,1B', 'notes' => 'nullable|string|max:3000', 'action' => 'required|in:save,activate']);
        $studentId = DB::transaction(function () use ($record, $data) {
            $record = SchoolRecord::lockForUpdate()->findOrFail($record->id);
            $existing = DB::table('admission_activations')->where('applicant_id', $record->id)->first();
            if ($existing) {
                return $existing->student_id;
            }
            if (! in_array($record->value('status'), ['Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan'])) {
                throw ValidationException::withMessages(['status' => 'Pendaftar harus lolos administrasi terlebih dahulu.']);
            }
            $complete = count(array_unique($data['checks'] ?? [])) === 4 && ! empty($data['paid']) && (int) $data['amount'] === (int) School::period()['fee'];
            if ($data['action'] === 'activate' && ! $complete) {
                throw ValidationException::withMessages(['checks' => 'Lengkapi pemeriksaan dokumen asli dan pembayaran sebelum aktivasi siswa.']);
            }
            $record->update(['data' => [...$record->data, ...$data, 'paid' => $complete || ! empty($data['paid']), 'payment' => ! empty($data['paid']) ? 'Tercatat' : 'Belum dicatat', 'status' => $complete ? 'Siap diaktifkan' : 'Menunggu daftar ulang']]);
            if ($data['action'] !== 'activate') {
                School::log('Menyimpan daftar ulang '.$record->code, 'PPDB', 'Tersimpan');

                return null;
            }
            $nis = 'SCN-'.substr($record->code, 5);
            $student = SchoolRecord::create(['kind' => 'students', 'code' => $nis, 'data' => [...$record->data, 'nis' => $nis, 'status' => 'Aktif', 'joined' => now()->toDateString(), 'applicant_id' => $record->id, 'address' => $record->value('address', ''), 'father' => $record->value('father', '')]]);
            DB::table('admission_activations')->insert(['applicant_id' => $record->id, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
            $invoice = SchoolRecord::create(['kind' => 'invoices', 'code' => 'INV-'.$record->code, 'data' => ['name' => 'Biaya pendaftaran PPDB · '.$student->value('name'), 'student_id' => $student->id, 'class' => $data['class'], 'amount' => (int) $data['amount'], 'period' => $record->value('year'), 'due_date' => now()->toDateString(), 'type' => 'PPDB']]);
            DB::table('school_payments')->insert(['invoice_id' => $invoice->id, 'reference' => 'KW-'.$record->code, 'amount' => (int) $data['amount'], 'method' => $data['method'], 'status' => 'Terverifikasi', 'received_at' => now()->toDateString(), 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $record->update(['data' => [...$record->data, 'status' => 'Aktif', 'student_id' => $student->id]]);
            School::log('Aktivasi '.$student->value('name'), 'Penerimaan siswa', 'Aktif');

            return $student->id;
        });

        return $studentId ? redirect('/admin/aktivasi/'.$studentId) : back()->with('success', 'Pemeriksaan daftar ulang disimpan.');
    }

    public function payment(Request $request)
    {
        $data = $request->validate(['invoice_id' => 'required|string', 'amount' => 'required|integer|min:1', 'received_at' => 'required|date|before_or_equal:today', 'reference' => 'required|string|max:100', 'method' => 'required|in:Tunai di sekolah,Transfer manual']);
        $id = DB::transaction(function () use ($data) {
            $invoice = SchoolRecord::ofKind('invoices')->lockForUpdate()->findOrFail($data['invoice_id']);
            if (DB::table('school_payments')->where('reference', $data['reference'])->exists()) {
                throw ValidationException::withMessages(['reference' => 'Referensi pembayaran sudah digunakan.']);
            }
            if ($data['amount'] > School::invoice($invoice)['balance']) {
                throw ValidationException::withMessages(['amount' => 'Nominal melebihi sisa tagihan.']);
            }
            $id = DB::table('school_payments')->insertGetId([...$data, 'status' => $data['method'] === 'Tunai di sekolah' ? 'Terverifikasi' : 'Menunggu', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            School::log('Mencatat pembayaran '.$data['reference'], 'Pembayaran', 'Tersimpan');

            return $id;
        });

        return redirect('/admin/pembayaran?tab=riwayat')->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function verifyPayment(Request $request, string $id)
    {
        $data = $request->validate(['notes' => 'required|string|max:3000', 'confirmed' => 'accepted']);
        DB::transaction(function () use ($id, $data) {
            $payment = DB::table('school_payments')->find($id);
            abort_unless($payment, 404);
            $invoice = SchoolRecord::ofKind('invoices')->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment = DB::table('school_payments')->lockForUpdate()->find($id);
            if ($payment->status === 'Terverifikasi') {
                return;
            }
            if ($payment->amount > School::invoice($invoice)['balance']) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran ini melebihi sisa tagihan; periksa pembayaran lain.']);
            }
            DB::table('school_payments')->where('id', $id)->update(['status' => 'Terverifikasi', 'review_notes' => $data['notes'], 'verified_by' => auth()->id(), 'verified_at' => now(), 'updated_at' => now()]);
            School::log('Memverifikasi '.$payment->reference, 'Pembayaran', 'Terverifikasi');
        });

        return back()->with('success', 'Pembayaran terverifikasi. Sisa tagihan diperbarui.');
    }

    public function receipt(string $id)
    {
        $payment = DB::table('school_payments')->where('status', 'Terverifikasi')->find($id);
        abort_unless($payment, 404);
        $invoice = SchoolRecord::findOrFail($payment->invoice_id);
        $student = SchoolRecord::find($invoice->value('student_id'));

        return view('admin.receipt', ['title' => 'Kuitansi Pembayaran', 'group' => 'finance', 'screen' => 'pembayaran', 'payment' => $payment, 'invoice' => $invoice, 'student' => $student]);
    }

    public function inspectPayment(string $id)
    {
        $payment = DB::table('school_payments')->find($id);
        abort_unless($payment, 404);
        $invoice = SchoolRecord::ofKind('invoices')->findOrFail($payment->invoice_id);

        return view('admin.payment-review', ['title' => 'Periksa Pembayaran', 'group' => 'finance', 'screen' => 'pembayaran', 'payment' => $payment, 'invoice' => $invoice]);
    }

    public function receiptPdf(string $id)
    {
        $payment = DB::table('school_payments')->where('status', 'Terverifikasi')->find($id);
        abort_unless($payment, 404);
        $invoice = SchoolRecord::ofKind('invoices')->findOrFail($payment->invoice_id);
        $student = SchoolRecord::ofKind('students')->findOrFail($invoice->value('student_id'));

        return PdfDocument::download('pdf.receipt', compact('payment', 'invoice', 'student'), $payment->reference.'.pdf');
    }

    public function reportPdf(SchoolRecord $record)
    {
        abort_unless($record->kind === 'students', 404);
        $grades = SchoolRecord::ofKind('grades')->where('data->student_id', $record->id)->where('data->status', 'Terbit')->get();

        return PdfDocument::download('pdf.report', compact('record', 'grades'), 'Rapor-'.$record->code.'.pdf');
    }

    public function document(string $id, \App\Services\FirestoreFileStorage $files)
    {
        $doc = DB::table('school_documents')->find($id);
        $file = $doc ? $files->get($doc->path) : null;
        abort_unless($file, 404);
        return response()->streamDownload(static function () use ($file) { echo $file['contents']; }, \Illuminate\Support\Str::ascii(basename($doc->name)), ['Content-Type' => $file['content_type'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function uploadDocument(Request $request, SchoolRecord $record, \App\Services\FirestoreFileStorage $files)
    {
        abort_unless(in_array($record->kind, ['students', 'staff', 'applicants']), 404);
        $request->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', 'name' => 'required|string|max:150']);
        $file = $request->file('document');
        $path = 'dashboard-documents/'.\Illuminate\Support\Str::uuid().'.'.$file->extension();
        DB::transaction(function () use ($record, $request, $file, $path, $files) {
            $files->putUploadedFile($path, $file);
            DB::table('school_documents')->insert(['record_id' => $record->id, 'name' => $request->name.'.'.$file->extension(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            School::log('Mengunggah dokumen '.$record->code, 'Dokumen', 'Tersimpan');
        });
        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function identity(SchoolRecord $record)
    {
        abort_unless(in_array($record->kind, ['students', 'staff']), 404);

        return view('admin.identity', compact('record'));
    }

    public function promotion(Request $request)
    {
        $data = $request->validate(['from' => 'required|string', 'to' => 'required|string|different:from', 'year' => 'required|regex:/^\d{4}\/\d{4}$/', 'confirm' => 'accepted']);
        $count = DB::transaction(function () use ($data) {
            $students = SchoolRecord::ofKind('students')->where('data->class', $data['from'])->lockForUpdate()->get();
            foreach ($students as $student) {
                $student->update(['data' => [...$student->data, 'class' => $data['to'], 'year' => $data['year'], 'status' => $data['to'] === 'Lulus' ? 'Lulus' : $student->value('status')]]);
            }
            School::log('Kenaikan kelas '.$data['from'].' → '.$data['to'],'Akademik','Selesai');

            return $students->count();
        });

        return redirect('/admin/kelas')->with('success',"Penempatan $count siswa diperbarui.");
    }
}
