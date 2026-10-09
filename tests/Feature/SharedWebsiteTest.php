<?php

namespace Tests\Feature;

use App\Services\WebsiteContent;
use App\Support\CloudData;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Tests\Support\CloudTestCase;

class SharedWebsiteTest extends CloudTestCase
{
    public function test_field_order_from_firestore_does_not_invalidate_editor_version(): void
    {
        $editor = app(WebsiteContent::class)->editor('home');
        $row = $this->store->get('content', 'home');
        $this->store->put('content', 'home', array_reverse($row, true));
        $this->assertSame($editor['version'], app(WebsiteContent::class)->editor('home')['version']);
    }

    public function test_remember_me_uses_new_cookie_token_for_each_login(): void
    {
        $provider = new \App\Auth\DocumentUserProvider;
        $user = $provider->retrieveById($this->admin()->id);
        $user->setRememberToken('first-token');
        $provider->updateRememberToken($user, 'first-token');
        $loaded = $provider->retrieveById($user->id);
        $this->assertSame('', $loaded->getRememberToken());
        $this->assertNotNull($provider->retrieveByToken($user->id, 'first-token'));
        $this->assertNull($provider->retrieveByToken($user->id, hash('sha256', 'first-token')));
        $provider->updateRememberToken($loaded, 'second-token');
        $this->assertNull($provider->retrieveByToken($user->id, 'first-token'));
        $this->assertNotNull($provider->retrieveByToken($user->id, 'second-token'));
    }
    public function test_editor_reads_live_nested_fields_and_publish_preserves_unknown_fields(): void
    {
        $row = $this->store->get('content', 'teachers');
        $row['team'][0]['name'] = 'Kepala Sekolah dari Firestore';
        $row['future_feature'] = ['enabled' => true, 'count' => 7];
        $this->store->put('content', 'teachers', $row);
        $this->actingAs($this->admin())->get('/admin/website/teachers/edit')->assertOk()->assertSee('Kepala Sekolah dari Firestore');
        $editor = app(WebsiteContent::class)->editor('teachers');
        $values = Arr::dot($editor['data']);
        $values = array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) $v, $values);
        $values['team.0.name'] = 'Nama baru';
        $this->post('/admin/website/teachers/edit', ['version' => $editor['version'], 'values' => $values])->assertSessionHasNoErrors();
        $this->assertSame('Kepala Sekolah dari Firestore', $this->store->get('content', 'teachers')['team'][0]['name']);
        app(WebsiteContent::class)->publish();
        $this->assertSame('Nama baru', $this->store->get('content', 'teachers')['team'][0]['name']);
        $this->assertSame(['enabled' => true, 'count' => 7], $this->store->get('content', 'teachers')['future_feature']);
    }

    public function test_publish_merges_unrelated_landing_edits_and_rejects_conflicting_edits_atomically(): void
    {
        $content = app(WebsiteContent::class);
        $editor = $content->editor('home');
        $content->save('home', [...$editor['data'], 'title' => 'Draf dashboard'], $editor['version']);
        $live = $this->store->get('content', 'home');
        $this->store->put('content', 'home', [...$live, 'description' => 'Diubah landing page']);
        $content->publish();
        $this->assertSame('Diubah landing page', $this->store->get('content', 'home')['description']);
        $this->assertSame('Draf dashboard', $this->store->get('content', 'home')['title']);
        $editor = $content->editor('home');
        $content->save('home', [...$editor['data'], 'title' => 'Draf kedua'], $editor['version']);
        $live = $this->store->get('content', 'home');
        $this->store->put('content', 'home', [...$live, 'title' => 'Judul dari landing']);
        try { $content->publish(); $this->fail('Konflik seharusnya ditolak.'); }
        catch (ValidationException) {
            $this->assertSame('Judul dari landing', $this->store->get('content', 'home')['title']);
            $this->assertNotNull($this->store->get('dashboard_content_drafts', 'home'));
        }
    }

    public function test_stale_editor_and_database_outage_do_not_overwrite_data(): void
    {
        $content = app(WebsiteContent::class);
        $first = $content->editor('home');
        $content->save('home', [...$first['data'], 'title' => 'Editor satu'], $first['version']);
        $this->actingAs($this->admin())->post('/admin/website/home/edit', [
            'version' => $first['version'], 'values' => Arr::dot([...$first['data'], 'title' => 'Editor dua']),
        ])->assertSessionHasErrors('content');
        $this->assertSame('Editor satu', $this->store->get('dashboard_content_drafts', 'home')['data']['title']);
        $this->assertSame('disabled', config('database.default'));
    }

    public function test_landing_application_with_string_id_is_visible_and_reviewed_in_same_document(): void
    {
        $this->store->put('applications', 'CN-LANDING-123', [
            'reference' => 'CN-LANDING-123', 'child' => ['child_name' => 'Anak dari landing', 'birth_date' => '2020-01-01'],
            'parent' => ['parent_name' => 'Wali landing', 'email' => 'parent@example.com'], 'status' => 'baru',
            'consent_at' => '2026-10-09', 'created_at' => now()->toIso8601String(),
        ]);
        CloudData::clear();
        $this->actingAs($this->admin())->get('/admin/pendaftar')->assertOk()->assertSee('Anak dari landing');
        $this->post('/admin/ppdb/CN-LANDING-123/review', ['decision' => 'approve', 'notes' => 'Sesuai'])->assertSessionHasNoErrors();
        $row = $this->store->get('applications', 'CN-LANDING-123');
        $this->assertSame('diterima', $row['status']);
        $this->assertSame('Sesuai', $row['admin_notes']);
        $this->assertSame('2026-10-09', $row['consent_at']);
    }

    public function test_inactive_account_cannot_log_in_and_password_reset_uses_firestore(): void
    {
        $admin = $this->admin();
        $row = $this->store->get('admins', $admin->id);
        $this->store->put('admins', $admin->id, [...$row, 'active' => false]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'Ceria2026!'])->assertSessionHasErrors('email');
        $this->store->put('admins', $admin->id, [...$row, 'active' => true]);
        $token = \Illuminate\Support\Facades\Password::createToken($admin);
        $this->post('/reset-password', ['email' => $admin->email, 'token' => $token, 'password' => 'ChangedPassword2026!', 'password_confirmation' => 'ChangedPassword2026!'])->assertRedirect('/login');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('ChangedPassword2026!', $this->store->get('admins', $admin->id)['password']));
    }
}
