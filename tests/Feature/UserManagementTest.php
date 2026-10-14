<?php

namespace Tests\Feature;

use App\Auth\DocumentUserProvider;
use App\Models\SchoolRecord;
use App\Models\User;
use App\Support\CloudData;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CloudTestCase;

class UserManagementTest extends CloudTestCase
{
    private function owner(): User
    {
        $id = hash('sha256', 'owner@example.test');
        if (!$this->store->get('admins', $id)) {
            $this->store->put('admins', $id, [
                'name' => 'Pemilik Sekolah', 'email' => 'owner@example.test', 'role' => 'owner',
                'active' => true, 'password' => Hash::make('Owner2026!'), 'remember_token' => '',
                'created_at' => now()->toIso8601String(),
            ]);
            CloudData::clear();
        }

        return User::find($id);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Admin Baru', 'email' => 'new@example.test', 'role' => 'admin', 'active' => '1',
            'password' => 'Baru2026!', 'password_confirmation' => 'Baru2026!',
        ], $overrides);
    }

    public function test_owner_can_create_admin_and_new_account_can_log_in(): void
    {
        $this->actingAs($this->owner())->get('/admin/pengguna')->assertOk()->assertSee('Tambah akun');
        $this->get('/admin/pengguna/baru')->assertOk()->assertSee('Buat akun')->assertSee('Owner')->assertSee('Admin');
        $this->post('/admin/pengguna', $this->payload(['email' => ' NEW@Example.test ']))
            ->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();
        $user = User::find(hash('sha256', 'new@example.test'));
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->active);
        $this->assertSame('new@example.test', $user->email);
        $this->assertTrue(Hash::check('Baru2026!', $user->password));
        $this->assertNotSame('Baru2026!', $user->password);
        $this->get('/admin/pengguna')->assertSee('new@example.test')->assertDontSee($user->password);
        $this->assertSame('Pemilik Sekolah', SchoolRecord::ofKind('activities')->get()->first(fn ($r) => $r->value('section') === 'users')->value('author'));
        $this->assertStringNotContainsString('Baru2026!', json_encode(SchoolRecord::ofKind('activities')->get()));

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => 'NEW@example.test', 'password' => 'Baru2026!'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk();
        $this->get('/admin/pengguna')->assertOk()->assertDontSee('Tambah akun')->assertDontSee('Kelola akun Admin Baru');
    }

    public function test_owner_can_create_another_owner_with_full_management_access(): void
    {
        $this->actingAs($this->owner())->post('/admin/pengguna', $this->payload(['role' => 'owner']))
            ->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();
        $owner = User::find(hash('sha256', 'new@example.test'));
        $this->assertTrue($owner->isOwner());
        $this->actingAs($owner)->get('/admin/pengguna/baru')->assertOk();
        $this->post('/admin/pengguna', $this->payload(['email' => 'third@example.test']))->assertSessionHasNoErrors();
        $this->assertNotNull(User::find(hash('sha256', 'third@example.test')));
    }

    public function test_guests_admins_and_other_roles_cannot_manage_users(): void
    {
        $url = '/admin/pengguna/'.$this->admin()->id;
        $this->get('/admin/pengguna/baru')->assertRedirect('/login');
        $this->get($url.'/edit')->assertRedirect('/login');
        $this->post('/admin/pengguna', $this->payload())->assertRedirect('/login');
        $this->put($url, $this->payload())->assertRedirect('/login');
        $before = $this->store->all('admins');
        foreach ([$this->admin(), new User(['id' => 'parent', 'name' => 'Wali', 'role' => 'parent', 'active' => true])] as $user) {
            $this->actingAs($user)->get('/admin/pengguna/baru')->assertForbidden();
            $this->get($url.'/edit')->assertForbidden();
            $this->post('/admin/pengguna', $this->payload(['role' => 'owner']))->assertForbidden();
            $this->put($url, $this->payload(['role' => 'owner']))->assertForbidden();
        }
        $this->assertSame($before, $this->store->all('admins'));
        $this->actingAs($this->admin())->get('/admin/pengguna')->assertOk()->assertDontSee('Tambah akun');
    }

    public function test_invalid_or_duplicate_accounts_are_rejected_without_overwriting_users(): void
    {
        $this->actingAs($this->owner());
        $before = $this->store->all('admins');
        $this->from('/admin/pengguna/baru')->post('/admin/pengguna', $this->payload([
            'name' => '   ', 'email' => 'invalid', 'role' => 'teacher', 'active' => 'yes',
            'password' => 'short', 'password_confirmation' => 'different',
        ]))->assertSessionHasErrors(['name', 'email', 'role', 'active', 'password']);
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->post('/admin/pengguna', $this->payload(['role' => 'superadmin']))->assertSessionHasErrors('role');
        $this->post('/admin/pengguna', $this->payload(['email' => mb_strtoupper($this->admin()->email)]))
            ->assertSessionHasErrors('email');
        $this->assertSame($before, $this->store->all('admins'));
    }

    public function test_owner_can_change_roles_and_deactivate_then_reactivate_an_account(): void
    {
        $admin = $this->admin();
        $row = $this->store->get('admins', $admin->id);
        $this->store->put('admins', $admin->id, $row + ['remember_token' => hash('sha256', 'old-token'), 'photo_path' => 'photos/existing.jpg']);
        $url = '/admin/pengguna/'.$admin->id;
        $this->actingAs($this->owner())->get($url.'/edit')->assertOk()->assertSee($admin->email);
        $this->put($url, $this->payload(['name' => 'Pengelola Baru', 'role' => 'owner', 'active' => '0']))
            ->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();
        $fresh = User::find($admin->id);
        $this->assertTrue($fresh->isOwner());
        $this->assertFalse($fresh->active);
        $this->assertSame('Pengelola Baru', $fresh->name);
        $this->assertSame($admin->email, $fresh->email);
        $this->assertSame($admin->password, $fresh->password);
        $this->assertSame('photos/existing.jpg', $fresh->photo_path);
        $this->assertSame('', $fresh->remember_token);
        $provider = new DocumentUserProvider;
        $this->assertNull($provider->retrieveById($admin->id));
        $this->assertNull($provider->retrieveByToken($admin->id, 'old-token'));
        $this->assertNull($provider->retrieveByCredentials(['email' => $admin->email]));
        $this->actingAs($fresh)->get('/admin')->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->owner())->put($url, $this->payload(['role' => 'admin', 'active' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($provider->retrieveByCredentials(['email' => $admin->email]));
        $this->assertSame('admin', User::find($admin->id)->role);
    }

    public function test_owner_can_create_inactive_account_and_duplicate_email_remains_reserved(): void
    {
        $this->actingAs($this->owner())->post('/admin/pengguna', $this->payload(['active' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse(User::find(hash('sha256', 'new@example.test'))->active);
        $this->post('/admin/pengguna', $this->payload())->assertSessionHasErrors('email');
        $this->post('/logout');
        $this->post('/login', ['email' => 'new@example.test', 'password' => 'Baru2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_existing_sessions_reload_role_and_active_status(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/pengguna', $this->payload(['role' => 'owner']))->assertSessionHasNoErrors();
        $id = hash('sha256', 'new@example.test');
        $this->put('/admin/pengguna/'.$id, $this->payload(['role' => 'admin']))->assertSessionHasNoErrors();
        $sessionKey = $this->app['auth']->guard()->getName();
        $this->withSession([$sessionKey => $id]);
        $this->app['auth']->forgetGuards();
        $this->get('/admin/pengguna/baru')->assertForbidden();
        $this->get('/admin')->assertOk();

        $this->actingAs($owner)->put('/admin/pengguna/'.$id, $this->payload(['active' => '0']))->assertSessionHasNoErrors();
        $this->withSession([$sessionKey => $id]);
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_last_active_owner_is_preserved_and_ownership_can_be_transferred(): void
    {
        $owner = $this->owner();
        $url = '/admin/pengguna/'.$owner->id;
        $this->actingAs($owner)->put($url, $this->payload(['role' => 'admin']))->assertSessionHasErrors('role');
        $this->put($url, $this->payload(['role' => 'owner', 'active' => '0']))->assertSessionHasErrors('role');
        $this->assertTrue(User::find($owner->id)->active);
        $this->assertTrue(User::find($owner->id)->isOwner());
        $this->post('/admin/pengguna', $this->payload(['role' => 'owner', 'active' => '0']))->assertSessionHasNoErrors();
        $this->put($url, $this->payload())->assertSessionHasErrors('role');
        $this->put('/admin/pengguna/'.hash('sha256', 'new@example.test'), $this->payload(['role' => 'owner']))->assertSessionHasNoErrors();
        $this->put($url, $this->payload())->assertSessionHasNoErrors();
        $this->assertFalse(User::find($owner->id)->isOwner());
        $this->actingAs(User::find($owner->id))->get('/admin/pengguna/baru')->assertForbidden();
    }

    public function test_owner_can_deactivate_own_account_when_another_owner_exists_and_is_logged_out(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/pengguna', $this->payload(['role' => 'owner']))->assertSessionHasNoErrors();
        $this->put('/admin/pengguna/'.$owner->id, $this->payload(['role' => 'owner', 'active' => '0']))->assertRedirect('/login');
        $this->assertGuest();
        $this->assertFalse(User::find($owner->id)->active);
    }

    public function test_revoked_owner_cannot_save_using_stale_authentication(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->store->put('admins', $owner->id, array_replace($this->store->get('admins', $owner->id), ['role' => 'admin']));
        $before = $this->store->all('admins');
        $this->post('/admin/pengguna', $this->payload())->assertForbidden();
        $this->put('/admin/pengguna/'.$this->admin()->id, $this->payload(['role' => 'owner']))->assertForbidden();
        $this->assertSame($before, $this->store->all('admins'));
    }

    public function test_unknown_accounts_and_unsupported_roles_cannot_be_updated(): void
    {
        $this->actingAs($this->owner())->get('/admin/pengguna/missing/edit')->assertNotFound();
        $this->put('/admin/pengguna/missing', $this->payload())->assertNotFound();
        $this->put('/admin/pengguna/'.$this->admin()->id, $this->payload(['role' => 'teacher']))->assertSessionHasErrors('role');
        $this->assertSame('admin', $this->admin()->role);
    }
}
