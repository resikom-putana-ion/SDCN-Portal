<?php

namespace Tests\Feature;

use App\Auth\DocumentUserProvider;
use App\Exceptions\FirebaseAuthException;
use App\Models\User;
use App\Services\FirebaseAccountMigration;
use App\Services\FirebaseAuth;
use App\Services\FirebaseCredentials;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\CloudTestCase;

class FirebaseAuthenticationTest extends CloudTestCase
{
    private array $accounts = [];
    private bool $unavailable = false;
    private bool $failImport = false;
    private array $resetEmails = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['school.firebase_auth_enabled' => true, 'school.firebase_api_key' => 'test-key', 'school.firebase_project' => 'test-project']);
        $this->mock(FirebaseCredentials::class, function ($mock) {
            $mock->shouldReceive('accessToken')->andReturn('test-access-token');
            $mock->shouldReceive('httpOptions')->andReturn([]);
        });
        Http::fake(['identitytoolkit.googleapis.com/*' => function (Request $request) {
            if ($this->unavailable) return Http::response(['error' => ['message' => 'UNAVAILABLE']], 503);
            $operation = explode(':', parse_url($request->url(), PHP_URL_PATH))[1] ?? '';
            $body = $request->data();
            if ($operation === 'lookup') {
                return Http::response(['users' => array_values(array_filter($this->accounts, fn ($a) =>
                    in_array($a['localId'], $body['localId'] ?? []) || in_array($a['email'], $body['email'] ?? [])))]);
            }
            if ($operation === 'signInWithPassword') {
                foreach ($this->accounts as $account) {
                    if ($account['email'] === $body['email'] && ! $account['disabled'] && Hash::check($body['password'], $account['testHash'])) {
                        return Http::response(['localId' => $account['localId'], 'email' => $account['email'], 'idToken' => 'secret-id-token', 'refreshToken' => 'secret-refresh-token']);
                    }
                }
                return Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400);
            }
            if ($operation === 'sendOobCode') {
                $this->resetEmails[] = $body['email'];
                $this->assertSame('PASSWORD_RESET', $body['requestType']);
                $this->assertSame('id', $request->header('X-Firebase-Locale')[0]);
                return Http::response(['email' => $body['email']]);
            }
            if ($operation === 'signUp') {
                foreach ($this->accounts as $account) {
                    if ($account['email'] === $body['email']) return Http::response(['error' => ['message' => 'EMAIL_EXISTS']], 400);
                }
                $this->accounts[$body['localId']] = $body + ['validSince' => '1', 'passwordUpdatedAt' => 1000, 'testHash' => Hash::make($body['password'])];
                unset($this->accounts[$body['localId']]['password']);
                return Http::response(['localId' => $body['localId']]);
            }
            if ($operation === 'batchCreate') {
                if ($this->failImport) return Http::response(['error' => [['index' => 0, 'message' => 'INVALID_PASSWORD_HASH']]]);
                $this->assertSame('BCRYPT', $body['hashAlgorithm']);
                $this->assertFalse($body['allowOverwrite']);
                $this->assertTrue($body['sanityCheck']);
                foreach ($body['users'] as $row) $this->accounts[$row['localId']] = $row + ['validSince' => '1', 'passwordUpdatedAt' => 1000, 'testHash' => base64_decode($row['passwordHash'])];
                return Http::response([]);
            }
            if ($operation === 'update') {
                $this->accounts[$body['localId']]['disabled'] = $body['disableUser'];
                return Http::response(['localId' => $body['localId']]);
            }
            if ($operation === 'delete') {
                unset($this->accounts[$body['localId']]);
                return Http::response([]);
            }
            throw new \LogicException('Unexpected Firebase operation: '.$operation);
        }]);
    }

    private function account(string $role = 'owner', bool $active = true): User
    {
        $email = $role.'@example.test';
        $id = hash('sha256', $email);
        $uid = 'firebase-'.$role;
        $this->store->put('admins', $id, ['email' => $email, 'name' => $role, 'role' => $role, 'active' => $active, 'firebase_uid' => $uid, 'password' => '', 'remember_token' => '']);
        $this->accounts[$uid] = ['localId' => $uid, 'email' => $email, 'disabled' => ! $active, 'validSince' => '1', 'passwordUpdatedAt' => 1000, 'testHash' => Hash::make('Ceria2026!')];
        return User::find($id);
    }

    private function login(User $user, bool $remember = false): void
    {
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Ceria2026!', 'remember' => $remember])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session('firebase_auth_version'));
    }

    public function test_each_role_can_login_and_its_access_is_still_restricted(): void
    {
        foreach (array_keys(User::ROLES) as $index => $role) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($index + 1)]);
            $user = $this->account($role);
            $this->login($user);
            $this->app['auth']->forgetGuards();
            $this->get('/admin')->assertOk();
            $response = $this->get('/admin/pengguna/baru');
            $role === 'owner' ? $response->assertOk() : $response->assertForbidden();
            if ($role === 'akademik') $this->get('/admin/keuangan')->assertForbidden();
            if ($role === 'keuangan') $this->get('/admin/nilai')->assertForbidden();
            $this->post('/logout');
        }
        $this->assertStringNotContainsString('secret-refresh-token', json_encode(session()->all()));
    }

    public function test_firebase_password_is_authoritative_and_local_hash_cannot_bypass_it(): void
    {
        $user = $this->account();
        $row = $this->store->get('admins', $user->id);
        $this->store->put('admins', $user->id, [...$row, 'password' => Hash::make('ObsoletePassword!')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'ObsoletePassword!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        // A configuration rollback must not reactivate the old hash of a migrated user.
        config(['school.firebase_auth_enabled' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'ObsoletePassword!'])->assertSessionHasErrors('email');
        $this->login($user);
    }

    public function test_disabled_deleted_and_unlisted_firebase_users_cannot_access_dashboard(): void
    {
        $user = $this->account();
        $this->accounts[$user->firebase_uid]['disabled'] = true;
        $this->post('/login', ['email' => $user->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        unset($this->accounts[$user->firebase_uid]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        $user = $this->account();
        $this->store->delete('admins', $user->id);
        $this->post('/login', ['email' => $user->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_remote_password_change_invalidates_existing_session_and_remember_token(): void
    {
        $user = $this->account();
        $this->login($user, true);
        $authenticated = $this->app['auth']->user();
        $provider = new DocumentUserProvider;
        $provider->updateRememberToken($authenticated, 'known-remember-token');
        $this->assertNotNull($provider->retrieveByToken($user->id, 'known-remember-token'));
        $this->accounts[$user->firebase_uid]['passwordUpdatedAt'] = 2000;
        $this->accounts[$user->firebase_uid]['validSince'] = '2';
        $this->assertNull($provider->retrieveByToken($user->id, 'known-remember-token'));
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_legacy_session_requires_new_firebase_login(): void
    {
        $user = $this->account();
        $this->withSession([$this->app['auth']->guard()->getName() => $user->id]);
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_forgot_password_uses_firebase_without_smtp_and_throttles_per_account(): void
    {
        $user = $this->account();
        config(['mail.default' => 'log']);
        for ($i = 0; $i < 2; $i++) $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('sent', true);
        $this->assertSame([$user->email], $this->resetEmails);
        $this->get('/forgot-password')->assertSee('halaman yang dibuka dari email');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('sent', true);
        $this->assertCount(1, $this->resetEmails);
    }

    public function test_inactive_account_does_not_receive_reset_email(): void
    {
        $user = $this->account('admin', false);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('sent', true);
        $this->assertSame([], $this->resetEmails);
    }

    public function test_auth_outage_fails_closed_and_does_not_claim_email_was_sent(): void
    {
        $user = $this->account();
        $this->unavailable = true;
        $this->post('/login', ['email' => $user->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email')->assertSessionMissing('sent');
        $this->unavailable = false;
        $this->login($user);
        $this->unavailable = true;
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertStatus(503)->assertDontSee('test-key');
    }

    public function test_owner_creates_firebase_user_without_storing_password_in_firestore(): void
    {
        $this->login($this->account());
        $this->post('/admin/pengguna', ['name' => 'Akun Baru', 'email' => 'new@example.test', 'role' => 'akademik', 'active' => '1', 'password' => 'NewPassword!', 'password_confirmation' => 'NewPassword!'])
            ->assertRedirect('/admin/pengguna')->assertSessionHasNoErrors();
        $row = $this->store->get('admins', hash('sha256', 'new@example.test'));
        $this->assertSame('', $row['password']);
        $this->assertNotEmpty($row['firebase_uid']);
        $this->assertSame('new@example.test', $this->accounts[$row['firebase_uid']]['email']);
        $this->post('/logout');
        $this->post('/login', ['email' => 'new@example.test', 'password' => 'NewPassword!'])->assertRedirect('/admin');
        $this->get('/admin/pengguna/baru')->assertForbidden();
    }

    public function test_owner_deactivation_and_reactivation_sync_with_firebase(): void
    {
        $this->login($this->account());
        $target = $this->account('admin');
        $data = ['name' => 'Admin', 'role' => 'admin', 'active' => '0'];
        $this->put('/admin/pengguna/'.$target->id, $data)->assertSessionHasNoErrors();
        $this->assertTrue($this->accounts[$target->firebase_uid]['disabled']);
        $this->assertFalse(User::find($target->id)->active);
        $this->put('/admin/pengguna/'.$target->id, [...$data, 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse($this->accounts[$target->firebase_uid]['disabled']);
    }

    public function test_migration_preserves_password_and_role_and_is_idempotent(): void
    {
        $row = $this->store->get('admins', $this->admin()->id);
        $migration = app(FirebaseAccountMigration::class);
        $migration->migrate($row);
        $fresh = $this->store->get('admins', $row['id']);
        $this->assertSame('admin', $fresh['role']);
        $this->assertSame('', $fresh['password']);
        $this->assertTrue(Hash::check('Ceria2026!', $this->accounts[$fresh['firebase_uid']]['testHash']));
        $migration->migrate($fresh);
        $this->assertCount(1, $this->accounts);
        $this->post('/login', ['email' => $row['email'], 'password' => 'Ceria2026!'])->assertRedirect('/admin');
    }

    public function test_migration_conflict_or_partial_import_failure_keeps_local_credentials(): void
    {
        $row = $this->store->get('admins', $this->admin()->id);
        $this->failImport = true;
        try { app(FirebaseAccountMigration::class)->migrate($row); $this->fail('Expected failure'); }
        catch (FirebaseAuthException $e) { $this->assertSame('IMPORT_FAILED', $e->reason); }
        $this->assertSame($row, $this->store->get('admins', $row['id']));
        $this->accounts['unrelated'] = ['localId' => 'unrelated', 'email' => $row['email']];
        try { app(FirebaseAccountMigration::class)->migrate($row); $this->fail('Expected conflict'); }
        catch (FirebaseAuthException $e) { $this->assertSame('ACCOUNT_CONFLICT', $e->reason); }
        $this->assertSame($row, $this->store->get('admins', $row['id']));
    }
}
