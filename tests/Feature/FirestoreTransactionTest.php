<?php

namespace Tests\Feature;

use App\Services\FirestoreDocumentStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirestoreTransactionTest extends TestCase
{
    private function store(): FirestoreDocumentStore
    {
        config(['school.firebase_project' => 'demo-transactions', 'school.firebase_database' => '(default)', 'school.firebase_ca_bundle' => null]);
        Http::preventStrayRequests();
        return new class extends FirestoreDocumentStore { protected function accessToken(): string { return 'test-token'; } };
    }
    public function test_transaction_buffers_writes_overlays_reads_and_commits_atomically(): void
    {
        $store = $this->store(); $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $calls[] = $request;
            if (str_ends_with($request->url(), ':beginTransaction')) return Http::response(['transaction' => 'tx1']);
            if (str_ends_with($request->url(), ':commit')) return Http::response(['commitTime' => '2026-10-09T00:00:00Z']);
            $this->assertSame('GET', $request->method());
            $this->assertSame('tx1', $request['transaction']);
            return Http::response(['documents' => []]);
        });
        $store->transaction(function () use ($store) {
            $this->assertSame([], $store->all('invoices'));
            $store->put('invoices', 'one', ['amount' => 10]);
            $store->put('payments', 'one', ['amount' => 10]);
            $this->assertSame(10, $store->get('payments', 'one')['amount']);
            $this->assertCount(1, $store->all('invoices'));
            $store->put('invoices', 'one', ['amount' => 0]);
        });
        $this->assertCount(3, $calls);
        $commit = $calls[2];
        $this->assertSame('tx1', $commit['transaction']);
        $this->assertCount(2, $commit['writes']);
        $this->assertSame('0', data_get($commit['writes'][0], 'update.fields.amount.integerValue'));
        $this->assertFalse($store->inTransaction());
    }
    public function test_failed_callback_rolls_back_without_any_commit(): void
    {
        $store = $this->store();
        Http::fake(fn ($r) => Http::response(str_ends_with($r->url(), ':beginTransaction') ? ['transaction' => 'tx1'] : []));
        try { $store->transaction(function () use ($store) { $store->put('payments', 'one', ['amount' => 10]); throw new \RuntimeException('stop'); }); }
        catch (\RuntimeException $e) { $this->assertSame('stop', $e->getMessage()); }
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), ':commit'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), ':rollback'));
        $this->assertFalse($store->inTransaction());
    }
    public function test_aborted_commit_retries_with_a_new_transaction(): void
    {
        $store = $this->store(); $begins = 0; $commits = 0; $runs = 0;
        Http::fake(function ($r) use (&$begins, &$commits) {
            if (str_ends_with($r->url(), ':beginTransaction')) return Http::response(['transaction' => 'tx'.++$begins]);
            if (str_ends_with($r->url(), ':commit') && ++$commits === 1) return Http::response(['error' => ['status' => 'ABORTED']], 409);
            return Http::response([]);
        });
        $store->transaction(function () use ($store, &$runs) { $store->put('payments', 'one', ['attempt' => ++$runs]); });
        $this->assertSame(2, $runs);
        $this->assertSame(2, $begins);
        $this->assertFalse($store->inTransaction());
    }
}
