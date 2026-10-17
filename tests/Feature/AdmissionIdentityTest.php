<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Support\School;
use Tests\Support\CloudTestCase;

class AdmissionIdentityTest extends CloudTestCase
{
    public function test_landing_applicant_gets_an_independent_nis_and_an_openable_profile(): void
    {
        $this->store->put('applications', 'landing-applicant', [
            'reference'=>'CN-2026-RANDOM1234', 'status'=>'diterima',
            'child'=>['child_name'=>'SISWA DARI LANDING', 'birth_date'=>'2020-05-12', 'gender'=>'Perempuan'],
            'parent'=>['parent_name'=>'Wali siswa', 'phone'=>'081234567890'],
            'created_at'=>now()->toIso8601String(),
        ]);
        $this->actingAs($this->admin());
        $payload = ['checks'=>['family','birth','family_card','photo'], 'paid'=>1,
            'amount'=>School::period()['fee'], 'method'=>'Tunai di sekolah', 'class'=>'1A', 'action'=>'activate'];
        $response = $this->post('/admin/ppdb/landing-applicant/offline', $payload)->assertSessionHasNoErrors();
        $student = SchoolRecord::ofKind('students')->where('data->applicant_id', 'landing-applicant')->firstOrFail();
        $response->assertRedirect('/admin/aktivasi/'.$student->id);
        $this->assertMatchesRegularExpression('/^SCN-\d{4}-\d{3,}$/', $student->code);
        $this->assertStringNotContainsString('RANDOM1234', $student->code);
        $this->assertSame('CN-2026-RANDOM1234', $student->registrationNumber());
        $this->assertSame($student->code, $student->value('nis'));
        $this->assertSame(School::period()['year'], $student->value('year'));
        $this->assertSame('CN-2026-RANDOM1234', $this->store->get('applications', 'landing-applicant')['reference']);
        foreach (['', '/detail', '/tagihan', '/dokumen', '/edit'] as $tab) {
            $this->get('/admin/siswa/'.$student->id.$tab)->assertOk();
        }
        $this->get('/admin/siswa/'.$student->id)->assertSee('Nomor pendaftaran')->assertSee('CN-2026-RANDOM1234')->assertSee($student->code);
        $this->post('/admin/ppdb/landing-applicant/offline', $payload)->assertRedirect('/admin/aktivasi/'.$student->id);
        $this->assertCloudCount('admission_activations', 1);
    }

    public function test_sparse_legacy_student_cards_open_without_rewriting_their_identifiers(): void
    {
        $student = SchoolRecord::create(['kind'=>'students', 'code'=>'NIS-LAMA', 'data'=>['name'=>'SISWA LAMA', 'status'=>'Aktif', 'number'=>'CN-LEGACY']]);
        $this->actingAs($this->admin())->get('/admin/siswa/'.$student->id)->assertOk()->assertSee('NIS-LAMA')->assertSee('CN-LEGACY');
        $this->get('/admin/identitas/'.$student->id)->assertOk();
        $this->assertSame('NIS-LAMA', $student->fresh()->code);
    }

    public function test_numbering_skips_existing_and_archived_student_numbers(): void
    {
        SchoolRecord::create(['kind'=>'student_deletions', 'code'=>'SCN-2026-099', 'data'=>[]]);
        $this->assertSame('SCN-2026-100', \App\Services\StudentNumber::next('2026/2027'));
    }
}
