<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Models\User;
use Tests\Support\CloudTestCase;

class DashboardNavigationTest extends CloudTestCase
{
    private function assertBack(string $page, string $target, string $label): void
    {
        $this->get($page)->assertOk()->assertSee('href="'.e($target).'" data-dashboard-back', false)->assertSee($label);
    }

    public function test_profile_tabs_edit_and_cancel_return_to_their_parent_and_preserve_list_filters(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::ofKind('students')->first();
        $profile = '/admin/siswa/'.$student->id;
        $list = '/admin/siswa?q=ALYA&filter=Aktif&year=2026%2F2027';
        $this->get($list)->assertOk();
        $this->assertBack($profile, $list, 'Kembali ke daftar siswa');
        $this->assertBack($profile.'/dokumen', $profile, 'Kembali ke profil');
        $this->assertBack($profile.'/edit', $profile, 'Kembali ke profil');
        $this->get($profile.'/edit')->assertSee('href="'.$profile.'">Batal', false);
        // Reloaded/direct profile visits still go to the list, not back into edit.
        $this->assertBack($profile, $list, 'Kembali ke daftar siswa');
        $this->assertBack('/admin/siswa/baru', $list, 'Kembali ke daftar siswa');
        $this->assertBack('/admin/siswa', '/admin', 'Kembali ke beranda');
        $this->get('/admin')->assertOk()->assertDontSee('data-dashboard-back', false);
    }

    public function test_direct_entry_has_a_parent_without_requiring_browser_history(): void
    {
        $this->actingAs($this->admin());
        $staff = SchoolRecord::ofKind('staff')->first();
        $applicant = SchoolRecord::ofKind('applicants')->first();
        $this->assertBack('/admin/guru/'.$staff->id, '/admin/guru', 'Kembali ke daftar guru');
        $this->assertBack('/admin/pendaftar/'.$applicant->id, '/admin/pendaftar', 'Kembali ke daftar pendaftar');
        $this->assertBack('/admin/daftar-ulang/'.$applicant->id, '/admin/daftar-ulang', 'Kembali ke daftar ulang');
        $this->assertBack('/admin/website/teachers/edit', '/admin/website', 'Kembali ke kelola website');
        $this->assertBack('/admin/website/preview?page=teachers', '/admin/website/teachers/edit', 'Kembali ke editor');
        $this->assertBack('/admin/website/preview?page=privacy', '/admin/website/settings/edit', 'Kembali ke editor');
        $this->assertBack('/admin/publikasi', '/admin/website', 'Kembali ke kelola website');
        $this->assertBack('/admin/kenaikan', '/admin/kelas', 'Kembali ke daftar kelas');
        $this->assertBack('/admin/audit', '/admin/pengaturan', 'Kembali ke pengaturan');
        $this->assertBack('/admin/pengguna/baru', '/admin/pengguna', 'Kembali ke daftar pengguna');
        $this->assertBack('/admin/pengguna/'.$this->admin()->id.'/edit', '/admin/pengguna', 'Kembali ke daftar pengguna');
    }

    public function test_return_urls_accept_only_same_origin_parent_lists_and_known_filters(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::ofKind('students')->first();
        $page = '/admin/siswa/'.$student->id;
        foreach (['https://outside.test/admin/siswa?q=UNTRUSTED', 'http://localhost:9999/admin/siswa?q=UNTRUSTED', 'http://localhost/admin/siswa/baru'] as $referer) {
            $this->withHeader('referer', $referer);
            $this->assertBack($page, '/admin/siswa', 'Kembali ke daftar siswa');
        }
        $this->withHeader('referer', url('/admin/siswa').'?q=ALYA&filter=Aktif&return=https://outside.test&secret=x&year[]=invalid');
        $this->assertBack($page, '/admin/siswa?q=ALYA&filter=Aktif', 'Kembali ke daftar siswa');
    }

    public function test_deletion_payment_and_limited_role_pages_also_have_return_navigation(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::ofKind('students')->first();
        $this->assertBack('/admin/siswa/'.$student->id.'/hapus', '/admin/siswa/'.$student->id, 'Kembali ke profil');
        $invoiceId = \App\Support\CloudData::table('school_payments')->find('1')->invoice_id;
        $this->assertBack('/admin/kuitansi/1', '/admin/pembayaran?invoice='.$invoiceId, 'Kembali ke pembayaran');
        $this->get('/admin/pembayaran?tab=riwayat&invoice='.$invoiceId)->assertOk();
        $this->assertBack('/admin/kuitansi/1', '/admin/pembayaran?tab=riwayat&invoice='.$invoiceId, 'Kembali ke pembayaran');
        $this->assertBack('/admin/periksa-pembayaran/2', '/admin/pembayaran?tab=riwayat&invoice='.$invoiceId, 'Kembali ke pembayaran');
        $this->actingAs(new User(['id'=>'academic','name'=>'Akademik','role'=>'akademik','active'=>true]));
        $this->assertBack('/admin/guru/'.SchoolRecord::ofKind('staff')->first()->id, '/admin/guru', 'Kembali ke daftar guru');
        $this->get('/admin/website/home/edit')->assertForbidden();
    }
}
