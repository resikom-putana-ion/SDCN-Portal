<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Models\User;
use App\Services\FirestoreDocumentStore;
use App\Services\FirestoreFileStorage;
use App\Services\StudentDeletionService;
use App\Support\CloudData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CloudTestCase;
use Tests\Support\MemoryFirestore;

class StudentLifecycleTest extends CloudTestCase
{
    public function test_admin_can_deactivate_and_reactivate_a_student_without_losing_history(): void
    {
        $student = SchoolRecord::where('code', 'SCN-2026-001')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/siswa/'.$student->id.'/status', ['status' => 'Nonaktif'])
            ->assertSessionHasErrors('confirmed');

        $this->post('/admin/siswa/'.$student->id.'/status', ['status' => 'Nonaktif', 'confirmed' => 1])
            ->assertRedirect('/admin/siswa/'.$student->id);
        $this->assertSame('Nonaktif', $student->fresh()->value('status'));
        $this->get('/admin/siswa/'.$student->id)
            ->assertOk()
            ->assertSee('Aktifkan kembali')
            ->assertSee('Nonaktif');
        $this->assertSame(3, SchoolRecord::ofKind('grades')->where('data->student_id', $student->id)->count());

        $this->post('/admin/siswa/'.$student->id.'/status', ['status' => 'Aktif', 'confirmed' => 1])
            ->assertRedirect('/admin/siswa/'.$student->id);
        $this->assertSame('Aktif', $student->fresh()->value('status'));
    }

    public function test_permanent_deletion_removes_operational_profile_and_keeps_auditable_finance_and_grades(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::where('code', 'SCN-2026-001')->firstOrFail();
        $invoice = SchoolRecord::where('code', 'INV-2026-001')->firstOrFail();
        $verifiedPayment = CloudData::table('school_payments')->where('invoice_id', $invoice->id)->where('status', 'Terverifikasi')->first();
        $paymentCount = CloudData::table('school_payments')->where('invoice_id', $invoice->id)->count();
        $files = app(FirestoreFileStorage::class);

        $files->put('student-photos/delete-me.jpg', 'photo', 'image/jpeg');
        $files->put('dashboard-documents/delete-me.pdf', 'document', 'application/pdf');
        $files->put('applications/delete-me.pdf', 'application', 'application/pdf');
        $files->put('dashboard-documents/shared.pdf', 'shared', 'application/pdf');
        $student->update(['data' => [...$student->data, 'photo_path' => 'student-photos/delete-me.jpg', 'applicant_id' => 'app-delete']]);
        $this->store->put('applications', 'app-delete', [
            'reference' => 'PPDB-DELETE',
            'status' => 'diterima',
            'dashboard' => ['student_id' => $student->id],
            'documents' => ['birth' => ['name' => 'Akta.pdf', 'path' => 'applications/delete-me.pdf', 'size' => 11]],
        ]);
        $this->store->put('admission_activations', 'activation-delete', ['student_id' => $student->id, 'applicant_id' => 'app-delete']);
        $this->store->put('school_documents', 'document-delete', ['record_id' => $student->id, 'name' => 'Ijazah.pdf', 'path' => 'dashboard-documents/delete-me.pdf', 'size' => 8]);
        $this->store->put('school_documents', 'document-shared', ['record_id' => $student->id, 'name' => 'Bersama.pdf', 'path' => 'dashboard-documents/shared.pdf', 'size' => 6]);
        $admin = $this->store->get('admins', $this->admin()->id);
        $admin['photo_path'] = 'dashboard-documents/shared.pdf';
        $this->store->put('admins', $this->admin()->id, $admin);
        CloudData::clear();

        $this->get('/admin/siswa/'.$student->id.'/hapus')
            ->assertOk()
            ->assertSee('Siswa harus dinonaktifkan terlebih dahulu')
            ->assertSee('Arsip yang tetap disimpan');
        $this->delete('/admin/siswa/'.$student->id, [
            'nis' => $student->code,
            'reason' => 'Profil duplikat.',
            'confirmed' => 1,
        ])->assertSessionHasErrors('status');

        $this->post('/admin/siswa/'.$student->id.'/status', ['status' => 'Nonaktif', 'confirmed' => 1]);
        $this->delete('/admin/siswa/'.$student->id, [
            'nis' => 'NIS-SALAH',
            'reason' => 'Profil duplikat.',
            'confirmed' => 1,
        ])->assertSessionHasErrors('nis');
        $this->delete('/admin/siswa/'.$student->id, [
            'nis' => $student->code,
            'reason' => 'Profil duplikat yang sudah diverifikasi.',
            'confirmed' => 1,
        ])->assertRedirect('/admin/siswa');

        CloudData::clear();
        $this->assertNull(SchoolRecord::ofKind('students')->find($student->id));
        $this->assertFalse(SchoolRecord::ofKind('attendance')->where('data->student_id', $student->id)->exists());
        $this->assertNull($this->store->get('applications', 'app-delete'));
        $this->assertNull($this->store->get('admission_activations', 'activation-delete'));
        $this->assertNull($this->store->get('school_documents', 'document-delete'));
        $this->assertFalse($files->exists('student-photos/delete-me.jpg'));
        $this->assertFalse($files->exists('dashboard-documents/delete-me.pdf'));
        $this->assertFalse($files->exists('applications/delete-me.pdf'));
        $this->assertTrue($files->exists('dashboard-documents/shared.pdf'), 'A file referenced by another account must not be deleted.');

        $archivedInvoice = SchoolRecord::ofKind('invoices')->findOrFail($invoice->id);
        $this->assertNull($archivedInvoice->value('student_id', null));
        $this->assertTrue($archivedInvoice->value('student_record_archived'));
        $this->assertSame('SCN-2026-001', $archivedInvoice->value('student_snapshot.nis'));
        $this->assertNull($archivedInvoice->value('student_snapshot.deletion_reason', null));
        $this->assertSame($paymentCount, CloudData::table('school_payments')->where('invoice_id', $invoice->id)->count());

        $tombstone = SchoolRecord::ofKind('student_deletions')->where('data->original_student_id', $student->id)->firstOrFail();
        $this->assertSame($student->code, $tombstone->code);
        $this->assertSame('Profil duplikat yang sudah diverifikasi.', $tombstone->value('reason'));
        $this->assertSame($this->admin()->id, $tombstone->value('started_by'));

        $archivedGrades = SchoolRecord::ofKind('grades')->where('data->student_snapshot.nis', 'SCN-2026-001')->get();
        $this->assertCount(3, $archivedGrades);
        $this->assertTrue($archivedGrades->every(fn ($grade) => $grade->value('student_record_archived') && $grade->value('student_id', null) === null));
        $this->get('/admin/nilai')->assertOk()->assertSee('Alya Putri');
        $this->assertStringContainsString('Alya Putri', $this->get('/admin/export/grades')->streamedContent());
        $this->post('/admin/record/grades/'.$archivedGrades->first()->id, [])->assertStatus(409);
        $this->get('/admin/rapor-arsip/'.$tombstone->id.'/pdf')->assertOk();

        $this->get('/admin/kuitansi/'.$verifiedPayment->id)->assertOk()->assertSee('Alya Putri');
        $this->get('/admin/kuitansi/'.$verifiedPayment->id.'/pdf')->assertOk();
        $this->get('/admin/pembayaran?invoice='.$invoice->id)->assertOk()->assertSee('Alya Putri');

        $academicId = hash('sha256', 'academic-archive@example.test');
        $this->store->put('admins', $academicId, ['name' => 'Petugas Akademik', 'email' => 'academic-archive@example.test', 'password' => Hash::make('Password123!'), 'role' => 'akademik', 'active' => true]);
        $this->actingAs(User::find($academicId))->get('/admin/rapor-arsip/'.$tombstone->id.'/pdf')->assertOk();
    }

    public function test_unverified_applicant_link_is_not_deleted_with_student(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::where('code', 'SCN-2026-004')->firstOrFail();
        app(FirestoreFileStorage::class)->put('applications/unrelated.pdf', 'keep', 'application/pdf');
        $this->store->put('applications', 'unrelated', [
            'reference' => 'PPDB-OTHER',
            'dashboard' => ['student_id' => 'someone-else'],
            'documents' => ['birth' => ['path' => 'applications/unrelated.pdf']],
        ]);
        $student->update(['data' => [...$student->data, 'applicant_id' => 'unrelated', 'status' => 'Nonaktif']]);

        $this->delete('/admin/siswa/'.$student->id, [
            'nis' => $student->code,
            'reason' => 'Data uji salah input.',
            'confirmed' => 1,
        ])->assertRedirect('/admin/siswa');

        $this->assertNotNull($this->store->get('applications', 'unrelated'));
        $this->assertTrue(app(FirestoreFileStorage::class)->exists('applications/unrelated.pdf'));
    }

    public function test_restricted_roles_cannot_change_status_or_open_permanent_deletion(): void
    {
        $student = SchoolRecord::where('code', 'SCN-2026-001')->firstOrFail();
        foreach (['akademik', 'keuangan'] as $role) {
            $id = hash('sha256', $role.'@example.test');
            $this->store->put('admins', $id, [
                'name' => ucfirst($role),
                'email' => $role.'@example.test',
                'password' => Hash::make('Password123!'),
                'role' => $role,
                'active' => true,
            ]);
            $this->actingAs(User::find($id));
            $this->post('/admin/siswa/'.$student->id.'/status', ['status' => 'Nonaktif', 'confirmed' => 1])->assertForbidden();
            $this->get('/admin/siswa/'.$student->id.'/hapus')->assertForbidden();
            $this->delete('/admin/siswa/'.$student->id, ['nis' => $student->code, 'reason' => 'Tidak berwenang', 'confirmed' => 1])->assertForbidden();
        }
        $this->assertSame('Aktif', $student->fresh()->value('status'));
    }

    public function test_document_upload_rechecks_student_after_deletion_plan_starts(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::where('code', 'SCN-2026-004')->firstOrFail();
        $student->update(['data' => [...$student->data, 'status' => 'Nonaktif']]);
        app(StudentDeletionService::class)->begin((string) $student->id, $student->code, 'Data duplikat untuk pengujian.', $this->admin());
        $documentCount = count($this->store->all('school_documents'));
        $fileCount = count($this->store->all('uploaded_files'));

        $this->post('/admin/dokumen/'.$student->id, [
            'name' => 'Akta kelahiran',
            'document' => UploadedFile::fake()->create('akta.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);

        $this->assertCount($documentCount, $this->store->all('school_documents'));
        $this->assertCount($fileCount, $this->store->all('uploaded_files'));
    }

    public function test_large_deletion_stays_below_firestore_limit_and_can_resume(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::where('code', 'SCN-2026-004')->firstOrFail();
        $student->update(['data' => [...$student->data, 'status' => 'Nonaktif']]);
        for ($index = 0; $index < 710; $index++) {
            $this->store->put('dashboard_records', 'bulk-'.$index, [
                'kind' => 'attendance',
                'code' => 'BULK-'.$index,
                'data' => ['student_id' => $student->id, 'name' => $student->value('name'), 'status' => 'Hadir'],
            ]);
        }

        $capped = new CappedMemoryFirestore;
        $capped->documents = $this->store->documents;
        $this->store = $capped;
        $this->app->instance(FirestoreDocumentStore::class, $capped);
        CloudData::clear();
        $deletion = app(StudentDeletionService::class);
        $deletion->begin((string) $student->id, $student->code, 'Pembersihan data uji massal.', $this->admin());

        $this->assertFalse($deletion->continue((string) $student->id, 1));
        $this->assertNotNull(SchoolRecord::ofKind('students')->find($student->id)?->value('deletion_plan'));
        $this->assertTrue($deletion->continue((string) $student->id, 4));
        $this->assertNull(SchoolRecord::ofKind('students')->find($student->id));
        $this->assertLessThanOrEqual(500, $capped->maxWrites);
    }

    public function test_failed_batch_rolls_back_and_can_be_retried_without_orphans(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::where('code', 'SCN-2026-004')->firstOrFail();
        $student->update(['data' => [...$student->data, 'status' => 'Nonaktif']]);
        for ($index = 0; $index < 40; $index++) {
            $this->store->put('dashboard_records', 'retry-'.$index, [
                'kind' => 'attendance',
                'code' => 'RETRY-'.$index,
                'data' => ['student_id' => $student->id, 'name' => $student->value('name')],
            ]);
        }
        $capped = new CappedMemoryFirestore;
        $capped->documents = $this->store->documents;
        $this->store = $capped;
        $this->app->instance(FirestoreDocumentStore::class, $capped);
        CloudData::clear();
        $deletion = app(StudentDeletionService::class);
        $deletion->begin((string) $student->id, $student->code, 'Menguji pemulihan transaksi.', $this->admin());
        $before = $capped->documents;
        $capped->failAt = 10;

        try {
            $deletion->continue((string) $student->id, 1);
            $this->fail('Batch seharusnya gagal pada write yang disuntikkan.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Simulated write failure', $exception->getMessage());
        }
        $this->assertSame($before, $capped->documents, 'Transaksi gagal harus kembali persis ke marker dan data sebelum batch.');

        $capped->failAt = null;
        $this->assertTrue($deletion->continue((string) $student->id, 2));
        $this->assertNull(SchoolRecord::ofKind('students')->find($student->id));
        $this->assertFalse(SchoolRecord::ofKind('attendance')->where('data->student_id', $student->id)->exists());
        $this->assertCount(1, SchoolRecord::ofKind('student_deletions')->where('data->original_student_id', $student->id)->get());
    }
}

class CappedMemoryFirestore extends MemoryFirestore
{
    public int $maxWrites = 0;
    public ?int $failAt = null;
    private int $writes = 0;
    private bool $counting = false;

    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction()) {
            return $callback();
        }
        $this->writes = 0;
        $this->counting = true;
        try {
            return parent::transaction($callback);
        } finally {
            $this->maxWrites = max($this->maxWrites, $this->writes);
            $this->counting = false;
        }
    }

    public function put(string $collection, string $id, array $data): void
    {
        $this->write();
        parent::put($collection, $id, $data);
    }

    public function delete(string $collection, string $id): void
    {
        $this->write();
        parent::delete($collection, $id);
    }

    private function write(): void
    {
        if ($this->counting && ++$this->writes > 500) {
            throw new \RuntimeException('Simulated Firestore 500-write limit exceeded.');
        }
        if ($this->counting && $this->failAt === $this->writes) {
            throw new \RuntimeException('Simulated write failure.');
        }
    }
}
