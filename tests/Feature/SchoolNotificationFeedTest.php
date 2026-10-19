<?php

namespace Tests\Feature;

use App\Services\SchoolNotificationFeed;
use Tests\Support\CloudTestCase;

class SchoolNotificationFeedTest extends CloudTestCase
{
    public function test_grouped_feed_uses_queries_and_correct_outstanding_balances(): void
    {
        $admin = $this->admin();
        $this->store->put('dashboard_records', 'overdue', [
            'kind' => 'invoices', 'code' => 'TEST-OVERDUE', 'created_at' => now()->subYear()->toIso8601String(),
            'data' => ['name' => 'Tagihan uji', 'amount' => 500000, 'due_date' => now()->subDay()->toDateString()],
        ]);
        $this->store->put('school_payments', 'partial', [
            'invoice_id' => 'overdue', 'amount' => 200000, 'status' => 'Terverifikasi',
        ]);
        $before = $this->store->collectionReads;
        $items = app(SchoolNotificationFeed::class)->forUser($admin);
        $this->assertSame($before, $this->store->collectionReads, 'Feed must not scan entire collections.');
        $invoice = $items->firstWhere('id', 'invoice-overdue');
        $this->assertSame('tagihan', $invoice['category']);
        $this->assertStringContainsString('300.000', $invoice['description']);
        $this->assertNotEmpty($items->where('category', 'pendaftaran'));
        $this->assertNotEmpty($items->where('category', 'pembayaran'));
        $this->assertNotEmpty($this->store->queries);

        $this->actingAs($admin)->get('/admin/notifikasi')->assertOk()
            ->assertSee('notification-pendaftaran', false)->assertSee('notification-tagihan', false)
            ->assertSee('notification-pembayaran', false);
        $this->getJson('/admin/notifikasi/feed')->assertOk()->assertJsonStructure([
            'notifications' => [['id', 'category', 'title', 'description', 'href', 'time']], 'count', 'checked_at',
        ]);

        $this->store->put('school_payments', 'remaining', [
            'invoice_id' => 'overdue', 'amount' => 300000, 'status' => 'Terverifikasi',
        ]);
        $this->assertNull(app(SchoolNotificationFeed::class)->forUser($admin)->firstWhere('id', 'invoice-overdue'));
    }

    public function test_academic_role_has_no_finance_notifications_or_queries(): void
    {
        $user = $this->admin();
        $user->role = 'akademik';
        $this->store->queries = [];
        $items = app(SchoolNotificationFeed::class)->forUser($user);
        $this->assertEmpty($items->whereIn('category', ['tagihan', 'pembayaran', 'kas', 'pengaturan', 'website']));
        $this->assertNull($items->firstWhere('category', 'pendaftaran')['href']);
        $this->assertEmpty(array_filter($this->store->queries, fn ($query) => $query[0] === 'school_payments'));
    }

    public function test_cache_reuses_queries_expires_and_separates_roles(): void
    {
        $user = $this->admin();
        $feed = app(SchoolNotificationFeed::class);
        $feed->cachedForUser($user);
        $count = count($this->store->queries);
        $feed->cachedForUser($user);
        $this->assertCount($count, $this->store->queries);
        $user->role = 'akademik';
        $this->assertEmpty($feed->cachedForUser($user)->whereIn('category', ['tagihan', 'pembayaran', 'kas']));
        $this->assertGreaterThan($count, count($this->store->queries));
        $user->role = 'admin';
        $count = count($this->store->queries);
        $this->travel(21)->seconds();
        $feed->cachedForUser($user);
        $this->assertGreaterThan($count, count($this->store->queries));
    }
}
