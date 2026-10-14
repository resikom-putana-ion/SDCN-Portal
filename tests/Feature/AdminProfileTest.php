<?php

namespace Tests\Feature;

use App\Services\FirestoreFileStorage;
use App\Models\User;
use Tests\Support\CloudTestCase;

class AdminProfileTest extends CloudTestCase
{
    public function test_profile_updates_current_firestore_admin_and_dashboard_identity(): void
    {
        $admin = $this->admin();
        $password = $admin->password;
        $this->actingAs($admin)->post('/admin/profil-admin', ['name' => 'Siti Rahma'])->assertRedirect('/admin/pengaturan');
        $fresh = User::find($admin->id);
        $this->assertSame('Siti Rahma', $fresh->name);
        $this->assertSame($password, $fresh->password);
        $this->assertSame($admin->email, $fresh->email);
        $this->actingAs($fresh)->get('/admin')->assertOk()->assertSee('Siti Rahma')->assertSee('SR');
        $this->get('/admin/pengaturan')->assertOk()->assertSee('Simpan profil admin')->assertSee('data-photo-canvas', false);
    }

    public function test_cropped_photo_is_stored_and_served_privately(): void
    {
        $admin = $this->admin();
        $bytes = file_get_contents(base_path('tests/Fixtures/admin-profile.jpg'));
        $this->actingAs($admin)->post('/admin/profil-admin', ['name' => $admin->name, 'photo_crop' => 'data:image/jpeg;base64,'.base64_encode($bytes)])->assertSessionHasNoErrors();
        $fresh = User::find($admin->id);
        $this->assertSame($bytes, app(FirestoreFileStorage::class)->get($fresh->photo_path)['contents']);
        $this->actingAs($fresh)->get('/admin/foto-admin')->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($bytes);
        $this->get('/admin')->assertSee('/admin/foto-admin');
    }

    public function test_invalid_crop_and_unauthorized_updates_are_rejected(): void
    {
        $this->post('/admin/profil-admin', ['name' => 'Other'])->assertRedirect('/login');
        $this->get('/admin/foto-admin')->assertRedirect('/login');
        $this->actingAs($this->admin())->post('/admin/profil-admin', ['name' => 'Other', 'photo_crop' => 'invalid'])->assertSessionHasErrors('photo_crop');
        $this->assertSame('Admin Sekolah', $this->admin()->name);
        $parent = new User(['id' => 'parent', 'role' => 'parent', 'active' => true]);
        $this->actingAs($parent)->post('/admin/profil-admin', ['name' => 'Other'])->assertForbidden();
    }
}
