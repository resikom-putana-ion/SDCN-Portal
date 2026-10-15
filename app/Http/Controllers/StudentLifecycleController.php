<?php

namespace App\Http\Controllers;

use App\Models\SchoolRecord;
use App\Services\StudentDeletionService;
use App\Support\CloudData;
use App\Support\School;
use Illuminate\Http\Request;

class StudentLifecycleController extends Controller
{
    public function status(Request $request, SchoolRecord $record)
    {
        $this->authorizeStatus($request);
        abort_unless($record->kind === 'students', 404);
        $data = $request->validate(['status' => 'required|in:Aktif,Nonaktif', 'confirmed' => 'accepted']);
        CloudData::transaction(function () use ($record, $data) {
            $student = SchoolRecord::ofKind('students')->findOrFail($record->id);
            abort_if($student->value('deletion_plan'), 409, 'Penghapusan siswa sedang berlangsung.');
            if ($student->value('status') === $data['status']) return;
            $student->update(['data' => [...$student->data, 'status' => $data['status'],
                'status_changed_at' => now()->toIso8601String(), 'status_changed_by' => auth()->id()]]);
            School::log(($data['status'] === 'Aktif' ? 'Mengaktifkan kembali siswa ' : 'Menonaktifkan siswa ').$student->code, 'Siswa', $data['status']);
        });

        return redirect('/admin/siswa/'.$record->id)->with('success', $data['status'] === 'Aktif' ? 'Siswa berhasil diaktifkan kembali.' : 'Siswa dinonaktifkan. Data dan riwayatnya tetap tersimpan.');
    }

    public function confirmDelete(Request $request, SchoolRecord $record, StudentDeletionService $deletion)
    {
        $this->authorizePermanentDeletion($request);
        abort_unless($record->kind === 'students', 404);

        return view('admin.student-delete', [
            'title' => 'Hapus Siswa Permanen',
            'group' => 'school',
            'screen' => 'siswa',
            'record' => $record,
            'summary' => $deletion->preview($record),
        ]);
    }

    public function destroy(Request $request, SchoolRecord $record, StudentDeletionService $deletion)
    {
        $this->authorizePermanentDeletion($request);
        abort_unless($record->kind === 'students', 404);
        $data = $request->validate([
            'nis' => 'required|string|max:100',
            'reason' => 'required|string|max:500',
            'confirmed' => 'accepted',
        ]);

        $deletion->begin((string) $record->id, $data['nis'], $data['reason'], $request->user());
        try {
            $complete = $deletion->continue((string) $record->id);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect('/admin/siswa/'.$record->id.'/hapus')->withErrors([
                'delete' => 'Proses terhenti sementara. Kemajuan sudah disimpan; periksa data lalu klik lanjutkan penghapusan.',
            ]);
        }

        if (! $complete) {
            return redirect('/admin/siswa/'.$record->id.'/hapus')->with('success', 'Sebagian data sudah diproses dengan aman. Klik lanjutkan untuk menyelesaikan penghapusan.');
        }

        return redirect('/admin/siswa')->with('success', 'Siswa dihapus permanen. Riwayat nilai dan catatan keuangan tetap tersimpan sebagai arsip tanpa profil aktif.');
    }

    private function authorizeStatus(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['owner', 'admin'], true), 403);
    }

    private function authorizePermanentDeletion(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['owner', 'admin'], true), 403);
    }
}
