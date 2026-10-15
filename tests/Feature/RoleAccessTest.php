<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CloudTestCase;

class RoleAccessTest extends CloudTestCase
{
    public function test_finance_role_only_sees_and_opens_finance_features(): void
    {
        $this->actingAs($this->roleUser('keuangan'));

        $this->get('/admin')->assertOk()
            ->assertSee('role Keuangan')
            ->assertSee('Tagihan sekolah')
            ->assertSee('Pembayaran')
            ->assertSee('Kas & transaksi')
            ->assertDontSee('Penerimaan siswa')
            ->assertDontSee('Website sekolah')
            ->assertDontSee('Siswa & keluarga');

        foreach (['tagihan', 'pembayaran', 'kas', 'laporan', 'pengaturan', 'bahasa', 'notifikasi', 'cari?q=SPP'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }

        $this->get('/admin/laporan')->assertSee('Laporan keuangan')->assertDontSee('Laporan siswa & kelas');
        $this->get('/admin/export/invoices')->assertOk();

        foreach (['siswa', 'guru', 'absensi', 'nilai', 'pendaftar', 'website', 'pengguna', 'pengaturan?tab=sekolah', 'export/students'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }

        $this->post('/admin/record/students', [])->assertForbidden();
        $this->post('/admin/kenaikan-kelas', [])->assertForbidden();
    }

    public function test_academic_role_only_sees_and_opens_academic_features(): void
    {
        $student = SchoolRecord::ofKind('students')->firstOrFail();
        $this->actingAs($this->roleUser('akademik'));

        $this->get('/admin')->assertOk()
            ->assertSee('role Akademik')
            ->assertSee('Siswa & keluarga')
            ->assertSee('Nilai & rapor')
            ->assertDontSee('Penerimaan siswa')
            ->assertDontSee('Tagihan sekolah')
            ->assertDontSee('Website sekolah');

        foreach (['siswa', 'guru', 'kelas', 'absensi', 'nilai', 'kenaikan', 'laporan', 'pengaturan', 'bahasa', 'notifikasi', 'cari?q=Alya'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }

        $this->get('/admin/siswa/'.$student->id)->assertOk()->assertDontSee('SPP & tagihan');
        $this->get('/admin/laporan')->assertSee('Laporan siswa & kelas')->assertSee('Nilai & rapor')->assertDontSee('Laporan keuangan');
        $this->get('/admin/export/students')->assertOk();
        $this->get('/admin/export/grades')->assertOk();

        foreach (['siswa/'.$student->id.'/tagihan', 'tagihan', 'pembayaran', 'kas', 'pendaftar', 'website', 'pengguna', 'pengaturan?tab=sekolah', 'export/invoices'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }

        $this->post('/admin/record/invoices', [])->assertForbidden();
        $this->post('/admin/siswa/'.$student->id.'/status', [])->assertForbidden();
    }

    public function test_restricted_roles_are_fail_closed_for_unknown_and_cross_domain_actions(): void
    {
        $student = SchoolRecord::ofKind('students')->firstOrFail();

        $this->actingAs($this->roleUser('keuangan'));
        $this->get('/admin/siswa/'.$student->id)->assertForbidden();
        $this->get('/admin/rapor/'.$student->id.'/pdf')->assertForbidden();
        $this->get('/admin/website/preview')->assertForbidden();
        $this->get('/admin/tidak-dikenal')->assertForbidden();

        $this->actingAs($this->roleUser('akademik'));
        $this->post('/admin/pembayaran/catat', [])->assertForbidden();
        $this->post('/admin/website/publish', [])->assertForbidden();
        $this->get('/admin/tidak-dikenal')->assertForbidden();
    }

    public function test_owner_can_create_finance_and_academic_accounts(): void
    {
        $owner = $this->roleUser('owner');
        $this->actingAs($owner);

        foreach (['keuangan', 'akademik'] as $role) {
            $email = $role.'@example.test';
            $this->post('/admin/pengguna', [
                'name' => 'Petugas '.ucfirst($role),
                'email' => $email,
                'role' => $role,
                'active' => '1',
                'password' => 'Rahasia2026!',
                'password_confirmation' => 'Rahasia2026!',
            ])->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();

            $this->assertSame($role, User::find(hash('sha256', $email))->role);
        }
    }

    private function roleUser(string $role): User
    {
        $email = $role.'-access@example.test';
        $id = hash('sha256', $email);
        $this->store->put('admins', $id, [
            'id' => $id,
            'name' => 'Petugas '.ucfirst($role),
            'email' => $email,
            'password' => Hash::make('Rahasia2026!'),
            'role' => $role,
            'active' => true,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        return User::find($id);
    }
}
