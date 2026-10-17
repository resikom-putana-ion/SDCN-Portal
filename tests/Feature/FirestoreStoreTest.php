<?php

namespace Tests\Feature;

use App\Services\FirestoreDocumentStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirestoreStoreTest extends TestCase
{
    private string $credentials;
    protected function setUp(): void
    {
        parent::setUp();
        $this->credentials=tempnam(sys_get_temp_dir(),'firebase-test-');
        file_put_contents($this->credentials,'{}');
        config(['school.firebase_project'=>'demo-school','school.firebase_database'=>'(default)','school.firebase_credentials'=>$this->credentials]);
        Http::preventStrayRequests();
    }
    protected function tearDown(): void { unlink($this->credentials);parent::tearDown(); }

    private function store(): FirestoreDocumentStore
    {
        return new class extends FirestoreDocumentStore {
            protected function accessToken(): string { return 'test-access-token'; }
        };
    }

    public function test_types_round_trip_without_losing_nested_data(): void
    {
        $value=['name'=>'Ceria','active'=>true,'total'=>42,'nullable'=>null,'ratio'=>1.5,'files'=>[['name'=>'Akta','path'=>'private/file.pdf']],'empty'=>[]];
        $this->assertSame($value,FirestoreDocumentStore::decode(FirestoreDocumentStore::encode($value)));
    }
    public function test_document_rest_endpoints_pagination_auth_and_aggregate(): void
    {
        $base='https://firestore.googleapis.com/v1/projects/demo-school/databases/%28default%29/documents';
        Http::fake([
            $base.'/applications/missing'=>Http::response([],404),
            $base.'/applications/existing'=>Http::response(['name'=>'projects/demo-school/databases/(default)/documents/applications/existing','fields'=>['status'=>['stringValue'=>'baru']]]),
            $base.'/applications?*'=>Http::response(['documents'=>[['name'=>'projects/demo-school/databases/(default)/documents/applications/existing','fields'=>[]]],'nextPageToken'=>'next-token']),
            $base.':runAggregationQuery'=>Http::response([['result'=>['aggregateFields'=>['total'=>['integerValue'=>'123']]]]]),
        ]);
        $store=$this->store();
        $this->assertNull($store->get('applications','missing'));
        $this->assertSame('baru',$store->get('applications','existing')['status']);
        $page=$store->page('applications','cursor-token');
        $this->assertSame('next-token',$page['next']);
        $this->assertSame(123,$store->count('applications'));
        Http::assertSent(fn($request)=>$request->url()===$base.':runAggregationQuery' && $request->hasHeader('Authorization','Bearer test-access-token'));
        Http::assertSent(fn($request)=>str_contains($request->url(),'pageToken=cursor-token'));
        Http::assertSent(fn($request)=>($request['orderBy'] ?? null) === '__name__ asc');
    }
    public function test_create_conflict_is_not_overwritten_and_cloud_failures_do_not_fallback(): void
    {
        Http::fake(['*'=>Http::response([],409)]);
        $this->assertFalse($this->store()->create('admins','duplicate',['name'=>'other']));
        Http::fake(['*'=>Http::response(['error'=>['message'=>'unavailable']],503)]);
        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $this->store()->get('content','home');
    }

    public function test_custom_ca_bundle_is_used_for_firestore_requests(): void
    {
        config(['school.firebase_ca_bundle' => $this->credentials]);
        Http::fake(function ($request, $options) {
            $this->assertSame($this->credentials, $options['verify']);
            $this->assertSame('__name__ asc', $request['orderBy']);
            return Http::response([]);
        });
        $this->assertSame([], $this->store()->page('content')['items']);
    }

    public function test_ca_setting_cannot_disable_tls_verification(): void
    {
        config(['school.firebase_ca_bundle' => false]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIREBASE_CA_BUNDLE');
        $this->store()->page('content');
    }

    public function test_atomic_writes_use_update_time_or_absence_preconditions(): void
    {
        $base='https://firestore.googleapis.com/v1/projects/demo-school/databases/%28default%29/documents';
        Http::fake([$base.':commit'=>Http::sequence()
            ->push(['error'=>['status'=>'FAILED_PRECONDITION']],400)
            ->push(['writeResults'=>[]])->push(['writeResults'=>[]])]);
        $store=$this->store();
        $this->assertFalse($store->compareAndSwap('runtime_cache','key','2026-10-03T00:00:00Z',['payload'=>'new']));
        $this->assertTrue($store->compareAndSwap('runtime_cache','key',null,['payload'=>'new']));
        $this->assertTrue($store->deleteIfUnchanged('runtime_cache','key','2026-10-03T00:00:01Z'));
        Http::assertSent(fn($request)=>$request['writes'][0]['currentDocument']===['exists'=>false]
            && $request['writes'][0]['update']['name']==='projects/demo-school/databases/(default)/documents/runtime_cache/key');
        Http::assertSent(fn($request)=>($request['writes'][0]['delete'] ?? null)==='projects/demo-school/databases/(default)/documents/runtime_cache/key'
            && $request['writes'][0]['currentDocument']===['updateTime'=>'2026-10-03T00:00:01Z']);
    }

    public function test_file_chunks_and_metadata_are_written_in_one_firestore_commit(): void
    {
        $base='https://firestore.googleapis.com/v1/projects/demo-school/databases/%28default%29/documents';
        Http::fake([$base.':commit'=>Http::response(['writeResults'=>[], 'commitTime'=>'now'])]);

        $this->store()->putDocuments([
            'uploaded_file_chunks' => ['chunk-id' => ['data' => str_repeat('a', 64)]],
            'uploaded_files' => ['file-id' => ['chunk_count' => 1]],
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === $base.':commit'
            && count($request['writes']) === 2
            && $request['writes'][0]['update']['name'] === 'projects/demo-school/databases/(default)/documents/uploaded_file_chunks/chunk-id'
            && $request['writes'][1]['update']['name'] === 'projects/demo-school/databases/(default)/documents/uploaded_files/file-id');
    }

    public function test_expiry_sweep_returns_versions_and_is_bounded(): void
    {
        Http::fake(['*'=>Http::response([['document'=>['name'=>'projects/demo/databases/(default)/documents/runtime_sessions/key',
            'updateTime'=>'version-1','fields'=>['expires_at'=>['integerValue'=>'100']]]]])]);
        $rows=$this->store()->expiredSnapshots('runtime_sessions',200,1000);
        $this->assertSame('version-1',$rows[0]['version']);
        $this->assertSame('key',$rows[0]['data']['id']);
        Http::assertSent(fn($request)=>str_ends_with($request->url(),':runQuery') && $request['structuredQuery']['limit']===100
            && $request['structuredQuery']['where']['compositeFilter']['filters'][1]['fieldFilter']['value']['integerValue']==='200');
    }
}
