<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Services\FirestoreFileStorage;
use App\Support\CloudData;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CloudTestCase;

class AdmissionPaymentTest extends CloudTestCase
{
    private function application(?string $method = 'transfer'): void
    {
        app(FirestoreFileStorage::class)->put('applications/NEW/proof.pdf', '%PDF-1.4 proof', 'application/pdf');
        $this->store->put('applications', 'NEW', [
            'reference' => 'NEW', 'child' => ['child_name' => 'Anak Baru'],
            'parent' => ['parent_name' => 'Wali', 'email' => 'wali@example.com'],
            'status' => 'diterima', 'payment_method' => $method,
            'documents' => ['payment_proof' => ['path' => 'applications/NEW/proof.pdf', 'name' => 'Bukti transfer.pdf']],
        ]);
        CloudData::clear();
    }

    public function test_landing_payment_and_private_download_are_visible_and_cannot_be_overridden(): void
    {
        $this->application();
        $doc = CloudData::table('school_documents')->where('record_id', 'NEW')->first();
        $this->get('/admin/dokumen/'.$doc->id)->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/admin/pendaftar/NEW')->assertOk()->assertSee('Online · Transfer rekening')->assertSee('Unduh bukti transfer');
        $this->get('/admin/dokumen/'.$doc->id)->assertDownload('Bukti transfer.pdf');
        $this->post('/admin/ppdb/NEW/offline', ['checks' => ['family', 'birth', 'family_card', 'photo'], 'paid' => 1,
            'amount' => 250000, 'method' => 'Tunai di sekolah', 'class' => '1A', 'action' => 'activate'])->assertSessionHasNoErrors();
        $this->assertSame('Transfer rekening', CloudData::table('school_payments')->where('reference', 'KW-NEW')->first()->method);
        $this->assertSame('transfer', $this->store->get('applications', 'NEW')['payment_method']);
    }

    public function test_missing_method_is_not_assumed_to_be_offline(): void
    {
        $this->application(null);
        $this->actingAs($this->admin())->post('/admin/ppdb/NEW/offline', ['paid' => 1, 'amount' => 250000, 'class' => '1A', 'action' => 'activate'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_rejection_and_email_failure_preserve_decision_and_can_be_retried(): void
    {
        $this->application();
        config(['mail.admissions.enabled' => true, 'mail.admissions.from' => 'goreskom5@gmail.com']);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->actingAs($this->admin())->post('/admin/ppdb/NEW/review', ['decision' => 'reject', 'notes' => 'Berkas tidak memenuhi persyaratan.'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($text) => str_contains($text, 'gagal dikirim'));
        $this->assertSame('ditolak', $this->store->get('applications', 'NEW')['status']);
        $this->assertSame('Ditolak', SchoolRecord::findOrFail('NEW')->value('status'));
        Mail::shouldReceive('send')->once()->andReturnNull();
        $this->post('/admin/ppdb/NEW/review', ['decision' => 'reject', 'notes' => 'Berkas tidak memenuhi persyaratan.'])
            ->assertSessionHas('success', fn ($text) => str_contains($text, 'telah dikirim'));
        $this->assertNotEmpty(SchoolRecord::findOrFail('NEW')->value('result_email.sent_at'));
    }

    public function test_public_registration_requires_a_method_and_transfer_proof(): void
    {
        $this->post('/ppdb', [])->assertSessionHasErrors('payment_method');
        $this->post('/ppdb', ['payment_method' => 'transfer'])->assertSessionHasErrors('payment_proof');
        $this->post('/ppdb', ['payment_method' => 'offline', 'payment_proof' => \Illuminate\Http\UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 proof')])
            ->assertSessionHasErrors('payment_proof');
    }

    public function test_approved_result_is_sent_once_for_unchanged_review(): void
    {
        $this->application('offline');
        config(['mail.admissions.enabled' => true, 'mail.admissions.from' => 'goreskom5@gmail.com']);
        Mail::shouldReceive('send')->once()->with('emails.admission-result', \Mockery::type('array'), \Mockery::on(function ($callback) {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            $this->assertSame('wali@example.com', $message->getSymfonyMessage()->getTo()[0]->getAddress());
            $this->assertSame('goreskom5@gmail.com', $message->getSymfonyMessage()->getFrom()[0]->getAddress());
            return true;
        }));
        $this->actingAs($this->admin());
        for ($i = 0; $i < 2; $i++) {
            $this->post('/admin/ppdb/NEW/review', ['decision' => 'approve', 'notes' => 'Lengkap'])->assertSessionHasNoErrors();
        }
    }
}
