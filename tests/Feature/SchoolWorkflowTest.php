<?php
namespace Tests\Feature;
use App\Models\{SchoolRecord,User};
use App\Support\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;
class SchoolWorkflowTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }
    private function admin(): User { return User::where('email','admin@sdcerianusantara.sch.id')->firstOrFail(); }
    public function test_admin_routes_require_authentication_and_admin_role(): void {
        $this->get('/admin')->assertRedirect('/login');
        $user=User::factory()->create(); $user->forceFill(['role'=>'parent'])->save();
        $this->actingAs($user)->get('/admin/siswa')->assertForbidden();
    }
    public function test_login_and_logout_use_session_authentication(): void {
        $this->post('/login',['email'=>$this->admin()->email,'password'=>'wrong'])->assertSessionHasErrors('email');
        $this->post('/login',['email'=>$this->admin()->email,'password'=>'Ceria2026!'])->assertRedirect('/admin');
        $this->assertAuthenticated(); $this->post('/logout')->assertRedirect('/login'); $this->assertGuest();
    }
    public function test_admin_screens_render(): void {
        $this->actingAs($this->admin());
        foreach(['','siswa','siswa/1','siswa/1/kehadiran','siswa/1/tagihan','siswa/1/nilai','siswa/1/dokumen','siswa/1/edit','guru','guru/5','pendaftar','pendaftar/9','daftar-ulang/10','periode','tagihan','pembayaran','kas','kelas','kenaikan','absensi','nilai','pengumuman','agenda','laporan','pengaturan','pengguna','aktivitas','alat','audit','website','halaman','website/hero/edit','website/ppdb/edit','website/preview','berita','media','publikasi','bahasa','notifikasi'] as $route) {
            $this->get('/admin'.($route?'/'.$route:''))->assertOk();
        }
    }
    public function test_offline_checklist_and_payment_are_required_before_activation(): void {
        $this->actingAs($this->admin()); $applicant=SchoolRecord::where('code','PPDB-2027-013')->firstOrFail();
        $payload=['checks'=>['family'],'amount'=>250000,'method'=>'Tunai di sekolah','class'=>'1A','action'=>'activate'];
        $this->post('/admin/ppdb/'.$applicant->id.'/offline',$payload)->assertSessionHasErrors('checks');
        $this->assertDatabaseCount('admission_activations',0);
        $payload['checks']=['family','birth','family_card','photo']; $payload['paid']=1;
        $this->post('/admin/ppdb/'.$applicant->id.'/offline',$payload)->assertRedirect();
        $this->assertDatabaseCount('admission_activations',1);
        $this->assertSame('Aktif',$applicant->fresh()->value('status'));
        $student=SchoolRecord::where('code','SCN-2027-013')->firstOrFail();
        $this->assertSame('Indah Kirana',$student->value('guardian'));
        $this->post('/admin/ppdb/'.$applicant->id.'/offline',$payload)->assertRedirect('/admin/aktivasi/'.$student->id);
        $this->assertDatabaseCount('admission_activations',1);
        $this->assertSame(1,DB::table('school_payments')->where('reference','KW-PPDB-2027-013')->count());
    }
    public function test_unreviewed_applicant_cannot_skip_administration_review(): void {
        $this->actingAs($this->admin());$record=SchoolRecord::where('code','PPDB-2027-014')->firstOrFail();
        $this->post('/admin/ppdb/'.$record->id.'/offline',['checks'=>['family','birth','family_card','photo'],'paid'=>1,'amount'=>250000,'method'=>'Tunai di sekolah','class'=>'1A','action'=>'activate'])->assertSessionHasErrors('status');
        $this->post('/admin/ppdb/'.$record->id.'/review',['decision'=>'approve','notes'=>'Dokumen sesuai'])->assertRedirect('/admin/daftar-ulang/'.$record->id);
        $this->assertSame('Lolos administrasi',$record->fresh()->value('status'));$this->assertDatabaseCount('admission_activations',0);
    }
    public function test_payment_verification_is_idempotent_and_overpayment_is_rejected(): void {
        $this->actingAs($this->admin());$invoice=SchoolRecord::where('code','INV-2026-001')->firstOrFail();
        $payload=['invoice_id'=>$invoice->id,'amount'=>100001,'received_at'=>now()->toDateString(),'reference'=>'TEST-PAYMENT','method'=>'Tunai di sekolah'];
        $this->post('/admin/pembayaran/catat',$payload)->assertSessionHasErrors('amount');
        $payment=DB::table('school_payments')->where('reference','TRF-20261005-01')->first();
        $this->post('/admin/pembayaran/'.$payment->id.'/verify')->assertRedirect();
        $this->post('/admin/pembayaran/'.$payment->id.'/verify')->assertRedirect();
        $this->assertSame(0,School::invoice($invoice)['balance']);
        $this->assertSame(300000,School::invoice($invoice)['paid']);
        $this->get('/admin/kuitansi/'.$payment->id)->assertOk();
    }
    public function test_draft_is_private_until_review_checklist_is_complete(): void {
        $this->actingAs($this->admin());$hero=School::content(true)['hero'];$hero['title']='Judul baru belum terbit';
        $this->post('/admin/website/hero/edit',$hero)->assertSessionHasNoErrors();
        $this->get('/website')->assertDontSee('Judul baru belum terbit');
        $this->get('/admin/website/preview')->assertSee('Judul baru belum terbit');
        $this->post('/admin/website/publish',['checks'=>['text'],'notes'=>'Sudah diperiksa'])->assertSessionHasErrors('checks');
        $this->post('/admin/website/publish',['checks'=>['text','image','contact','period','offline'],'notes'=>'Sudah diperiksa'])->assertRedirect('/admin/terbit');
        $this->get('/website')->assertSee('Judul baru belum terbit');
    }
    public function test_public_application_stores_private_documents_and_no_active_student(): void {
        Storage::fake('local');$before=SchoolRecord::ofKind('students')->count();
        $payload=['name'=>'Siswa Uji','birth_date'=>'2020-03-12','guardian'=>'Wali Uji','relationship'=>'Ibu','phone'=>'08123456789','email'=>'wali@example.com','gender'=>'Perempuan','consent'=>1,'birth_certificate'=>UploadedFile::fake()->create('birth.pdf',10,'application/pdf'),'family_card'=>UploadedFile::fake()->create('family.pdf',10,'application/pdf'),'photo'=>UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))];
        $this->post('/ppdb',$payload)->assertSessionHasNoErrors()->assertRedirect('/ppdb/berhasil');
        $this->assertSame($before,SchoolRecord::ofKind('students')->count());
        $record=SchoolRecord::ofKind('applicants')->latest('id')->first();$this->assertSame('Pemeriksaan',$record->value('status'));
        $documents=DB::table('school_documents')->where('record_id',$record->id)->get();$this->assertCount(3,$documents);
        $this->get('/admin/dokumen/'.$documents[0]->id)->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/admin/dokumen/'.$documents[0]->id)->assertOk();
    }
    public function test_saved_student_is_searchable_and_csv_escapes_formulas(): void {
        $this->actingAs($this->admin());$student=SchoolRecord::ofKind('students')->first();$data=$student->data;$data['name']='=HYPERLINK("test")';
        $this->post('/admin/record/students/'.$student->id,$data)->assertSessionHasNoErrors();
        $this->get('/admin/siswa?q=HYPERLINK')->assertSee('HYPERLINK');
        $response=$this->get('/admin/export/students');$response->assertOk();$this->assertStringContainsString("'=HYPERLINK",$response->streamedContent());
    }
}
