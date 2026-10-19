<?php

namespace Tests\Feature;

use App\Services\FirestoreDocumentStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirestoreNotificationQueryTest extends TestCase
{
    public function test_filters_are_sent_to_firestore_and_empty_result_markers_are_ignored(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([
            ['document' => ['name' => 'projects/test/databases/(default)/documents/dashboard_records/a',
                'fields' => ['kind' => ['stringValue' => 'grades']]]],
            ['readTime' => '2026-10-19T00:00:00Z'],
        ])]);
        $store = new class extends FirestoreDocumentStore {
            protected function accessToken(): string { return 'test-token'; }
        };
        $rows = $store->query('dashboard_records', [['kind', 'EQUAL', 'grades'], ['data.status', 'EQUAL', 'Pemeriksaan']]);
        $this->assertSame([['id' => 'a', 'kind' => 'grades']], $rows);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), ':runQuery')
            && $request['structuredQuery']['where']['compositeFilter']['filters'][1]['fieldFilter'] === [
                'field' => ['fieldPath' => 'data.status'], 'op' => 'EQUAL', 'value' => ['stringValue' => 'Pemeriksaan'],
            ]);
    }
}
