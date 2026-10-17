<?php

namespace Tests\Feature;

use App\Auth\DocumentUserProvider;
use App\Exceptions\FirebaseAuthException;
use App\Models\SchoolRecord;
use App\Models\User;
use App\Services\FirebaseAuth;
use App\Services\FirestoreFileStorage;
use App\Support\CloudData;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CloudTestCase;

class AccountDeletionTest extends CloudTestCase
{
    private function account(string $role, string $name = 'test', array $extra = []): User
    {
        $email = $name.'@example.test';
        $id = hash('sha256', $email);
        $this->store->put('admins', $id, $extra + ['name' => $name, 'email' => $email, 'role' => $role, 'active' => true, 'password' => Hash::make('Password2026!'), 'remember_token' => '']);
        CloudData::clear();
        return User::find($id);
    }

    private function remove(array $users, string $confirmation = 'HAPUS')
    {
        return $this->delete('/admin/pengguna/hapus', ['accounts' => array_map(fn ($u) => $u->id, $users), 'confirmation' => $confirmation]);
    }

    public function test_owner_can_preview_and_delete_multiple_accounts_while_preserving_history(): void
    {
        $owner = $this->account('owner', 'owner');
        $finance = $this->account('keuangan', 'finance');
        $academic = $this->account('akademik', 'academic');
        $payments = $this->store->all('school_payments');
        $this->actingAs($owner)->post('/admin/pengguna/hapus/konfirmasi', ['accounts' => [$finance->id, $academic->id]])
            ->assertOk()->assertSee($finance->email)->assertSee($academic->email)->assertSee('Ketik HAPUS');
        $this->assertNotNull(User::find($finance->id));
        $this->remove([$finance, $academic])->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();
        $this->assertNull(User::find($finance->id));
        $this->assertNull(User::find($academic->id));
        $this->assertSame($payments, $this->store->all('school_payments'));
        $this->assertNotNull(User::find($owner->id));
        $this->assertCount(2, SchoolRecord::ofKind('activities')->get()->filter(fn ($r) => $r->value('status') === 'Dihapus permanen'));
    }

    public function test_admin_can_delete_other_admin_and_department_accounts_but_cannot_delete_owner(): void
    {
        $owner = $this->account('owner', 'owner');
        $other = $this->account('admin', 'other-admin');
        $department = $this->account('akademik', 'academic');
        $this->actingAs($this->admin())->get('/admin/pengguna')->assertOk()
            ->assertSee('/admin/pengguna/'.$other->id.'/hapus', false)
            ->assertDontSee('/admin/pengguna/'.$owner->id.'/hapus', false);
        $this->get('/admin/pengguna/'.$other->id.'/hapus')->assertOk()->assertSee($other->email);
        $this->remove([$owner])->assertSessionHasErrors('accounts');
        $this->assertNotNull(User::find($owner->id));
        $this->remove([$other, $department])->assertRedirect('/admin/pengguna');
        $this->assertNull(User::find($other->id));
        $this->assertNull(User::find($department->id));
        $this->assertNotNull(User::find($this->admin()->id));
    }

    public function test_self_or_last_owner_selection_is_rejected_before_deleting_any_account(): void
    {
        $owner = $this->account('owner', 'owner');
        $this->actingAs($owner);
        $before = $this->store->all('admins');
        $this->remove([$this->admin(), $owner])->assertSessionHasErrors('accounts');
        $this->assertSame($before, $this->store->all('admins'));
        $secondOwner = $this->account('owner', 'second-owner');
        $this->remove([$secondOwner])->assertRedirect('/admin/pengguna');
        $this->assertNull(User::find($secondOwner->id));
        $this->assertTrue(User::find($owner->id)->active);
    }

    public function test_guest_finance_and_academic_roles_cannot_delete_or_preview_accounts(): void
    {
        $target = $this->admin();
        $this->get('/admin/pengguna/'.$target->id.'/hapus')->assertRedirect('/login');
        $this->remove([$target])->assertRedirect('/login');
        foreach (['keuangan', 'akademik'] as $role) {
            $this->actingAs($this->account($role, $role));
            $this->get('/admin/pengguna/'.$target->id.'/hapus')->assertForbidden();
            $this->post('/admin/pengguna/hapus/konfirmasi', ['accounts' => [$target->id]])->assertForbidden();
            $this->remove([$target])->assertForbidden();
        }
        $this->assertNotNull(User::find($target->id));
    }

    public function test_confirmation_and_selection_are_required_and_deletion_rechecks_current_role(): void
    {
        $owner = $this->account('owner', 'owner');
        $target = $this->admin();
        $this->actingAs($owner);
        $this->remove([$target], '')->assertSessionHasErrors('confirmation');
        $this->remove([$target], 'hapus')->assertSessionHasErrors('confirmation');
        $this->remove([])->assertSessionHasErrors('accounts');
        $this->remove([$target, $target])->assertSessionHasErrors('accounts.0');
        $this->delete('/admin/pengguna/hapus', ['accounts' => [$target->id, 'missing'], 'confirmation' => 'HAPUS'])->assertNotFound();
        $row = $this->store->get('admins', $owner->id);
        $this->store->put('admins', $owner->id, [...$row, 'role' => 'keuangan']);
        $this->remove([$target])->assertForbidden();
        $this->assertTrue(User::find($target->id)->active);
    }

    public function test_firebase_account_and_profile_photo_are_removed(): void
    {
        $owner = $this->account('owner', 'owner');
        $target = $this->account('admin', 'firebase-admin', ['firebase_uid' => 'target-uid', 'photo_path' => 'admin-photos/test.jpg']);
        app(FirestoreFileStorage::class)->put('admin-photos/test.jpg', 'test-photo', 'image/jpeg');
        $this->mock(FirebaseAuth::class)->shouldReceive('delete')->once()->with('target-uid')->andReturnUsing(function () use ($target) {
            $this->assertFalse(User::find($target->id)->active);
            $this->assertTrue(User::find($target->id)->deletion_pending);
        });
        $this->actingAs($owner);
        $this->remove([$target])->assertSessionHasNoErrors();
        $this->assertNull(User::find($target->id));
        $this->assertFalse(app(FirestoreFileStorage::class)->exists('admin-photos/test.jpg'));
        $this->assertNull((new DocumentUserProvider)->retrieveById($target->id));
    }

    public function test_failed_firebase_deletion_blocks_access_and_can_be_retried_without_reactivation(): void
    {
        $owner = $this->account('owner', 'owner');
        $target = $this->account('admin', 'firebase-admin', ['firebase_uid' => 'target-uid']);
        $mock = $this->mock(FirebaseAuth::class);
        $mock->shouldReceive('delete')->once()->with('target-uid')->andThrow(new FirebaseAuthException);
        $this->actingAs($owner);
        $this->remove([$target])->assertSessionHasErrors('accounts');
        $this->assertFalse(User::find($target->id)->active);
        $this->assertTrue(User::find($target->id)->deletion_pending);
        $this->get('/admin/pengguna')->assertOk()->assertSee('Lanjutkan hapus');
        $this->put('/admin/pengguna/'.$target->id, ['name' => 'Restored', 'role' => 'owner', 'active' => '1'])->assertSessionHasErrors('active');
        $this->assertFalse(User::find($target->id)->active);
        $this->assertNull((new DocumentUserProvider)->retrieveById($target->id));
        // Covers an interrupted previous attempt whose Firebase deletion succeeded.
        $this->mock(FirebaseAuth::class)->shouldReceive('delete')->once()->with('target-uid')->andThrow(new FirebaseAuthException('USER_NOT_FOUND'));
        $this->remove([$target])->assertRedirect('/admin/pengguna');
        $this->assertNull(User::find($target->id));
    }

    public function test_a_deleted_accounts_existing_session_cannot_access_dashboard(): void
    {
        $owner = $this->account('owner', 'owner');
        $target = $this->account('admin', 'target');
        $this->actingAs($owner);
        $this->remove([$target])->assertRedirect('/admin/pengguna');
        $this->withSession([$this->app['auth']->guard()->getName() => $target->id]);
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_partial_bulk_failure_keeps_only_failed_account_pending(): void
    {
        $owner = $this->account('owner', 'owner');
        $first = $this->account('admin', 'first', ['firebase_uid' => 'first-uid']);
        $second = $this->account('keuangan', 'second', ['firebase_uid' => 'second-uid']);
        $mock = $this->mock(FirebaseAuth::class);
        $mock->shouldReceive('delete')->with('first-uid')->once()->andReturnNull();
        $mock->shouldReceive('delete')->with('second-uid')->once()->andThrow(new FirebaseAuthException);
        $this->actingAs($owner);
        $this->remove([$first, $second])->assertSessionHasErrors('accounts');
        $this->assertNull(User::find($first->id));
        $this->assertFalse(User::find($second->id)->active);
        $this->assertTrue(User::find($second->id)->deletion_pending);
        $this->get('/admin/pengguna')->assertSee('1 akun berhasil dihapus. 1 akun belum selesai dihapus');
    }

    public function test_admin_cannot_delete_account_promoted_to_owner_after_confirmation_preview(): void
    {
        $this->account('owner', 'owner');
        $target = $this->account('admin', 'target');
        $this->actingAs($this->admin())->get('/admin/pengguna/'.$target->id.'/hapus')->assertOk();
        $row = $this->store->get('admins', $target->id);
        $this->store->put('admins', $target->id, [...$row, 'role' => 'owner']);
        $this->remove([$target])->assertSessionHasErrors('accounts');
        $this->assertTrue(User::find($target->id)->active);
        $this->assertFalse(User::find($target->id)->deletion_pending);
    }
}
