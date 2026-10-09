<?php

namespace Tests\Feature;

use App\Services\FirestoreCacheStore;
use App\Services\FirestoreDocumentStore;
use App\Services\FirestoreSessionHandler;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirestoreRuntimeStoreTest extends TestCase
{
    private MemoryFirestore $documents;
    private FirestoreCacheStore $cache;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->freezeTime();
        $this->documents = new MemoryFirestore;
        $this->cache = new FirestoreCacheStore($this->documents, 'test-prefix');
    }

    public function test_cache_keeps_types_expiry_and_namespace_without_touching_other_documents(): void
    {
        $this->assertTrue($this->cache->putMany(['array'=>['name'=>'Sekolah','enabled'=>false], 'zero'=>0],30));
        $this->cache->forever('forever','kept');
        $other = new FirestoreCacheStore($this->documents, 'another-prefix');
        $other->put('array','other app',100);
        $this->documents->put('content','home',['title'=>'School']);
        $this->assertSame(['array'=>['name'=>'Sekolah','enabled'=>false],'zero'=>0,'missing'=>null], $this->cache->many(['array','zero','missing']));
        $this->travel(31)->seconds();
        $this->assertNull($this->cache->get('array'));
        $this->assertSame('kept',$this->cache->get('forever'));
        $this->assertSame(2,$this->cache->pruneExpired());
        $this->assertTrue($this->cache->flush());
        $this->assertNull($this->cache->get('forever'));
        $this->assertSame('other app',$other->get('array'));
        $this->assertSame('School',$this->documents->get('content','home')['title']);
    }

    public function test_atomic_increment_retries_a_conflict_and_preserves_expiration(): void
    {
        $this->assertTrue($this->cache->add('counter',0,60));
        $this->assertFalse($this->cache->add('counter',99,60));
        $this->documents->onCompare = function ($collection,$id) {
            $record=$this->documents->get($collection,$id);
            $record['payload']=base64_encode(serialize(4));
            $this->documents->put($collection,$id,$record);
        };
        $this->assertSame(5,$this->cache->increment('counter'));
        $this->assertSame(3,$this->cache->decrement('counter',2));
        $this->travel(61)->seconds();
        $this->assertFalse($this->cache->increment('counter'));
        $this->assertNull($this->cache->get('counter'));
        $this->assertTrue($this->cache->add('counter',1,20));
        $this->assertSame(1,$this->cache->get('counter'));
    }

    public function test_laravel_rate_limiter_uses_shared_counts_and_expires(): void
    {
        $first = new RateLimiter(new Repository($this->cache));
        $second = new RateLimiter(new Repository(new FirestoreCacheStore($this->documents,'test-prefix')));
        $this->assertSame(1,$first->hit('login:client',60));
        $this->assertSame(2,$second->hit('login:client',60));
        $this->assertTrue($first->tooManyAttempts('login:client',2));
        $this->travel(61)->seconds();
        $this->assertFalse($second->tooManyAttempts('login:client',2));
    }

    public function test_lock_ownership_survives_expiry_and_stale_release_cannot_delete_new_lock(): void
    {
        $first=$this->cache->lock('session:id',10);
        $second=$this->cache->lock('session:id',10);
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $this->assertFalse($second->release());
        $this->travel(11)->seconds();
        $this->assertTrue($second->get());
        $this->assertFalse($first->release());
        $this->assertTrue($second->isOwnedByCurrentProcess());
        $this->assertTrue($this->cache->restoreLock('session:id',$second->owner())->release());
        $this->assertTrue($first->get());
        $first->forceRelease();
        $this->assertTrue($second->get());
    }

    public function test_session_payload_survives_a_new_handler_and_is_removed_on_logout_or_expiry(): void
    {
        $session=new FirestoreSessionHandler($this->documents,2,'school-session');
        $another=new FirestoreSessionHandler($this->documents,2,'school-session');
        $id=str_repeat('a',40);
        $payload=serialize(['login'=>'admin-id','registration'=>['child_name'=>'Test Child']]);
        $this->assertTrue($session->write($id,$payload));
        $this->assertSame($payload,$another->read($id));
        $stored=$this->documents->page('runtime_sessions')['items'][0];
        $this->assertNotSame($id,$stored['id']);
        $this->assertTrue($session->destroy($id));
        $this->assertSame('',$another->read($id));
        $this->assertTrue($session->write($id,'0'));
        $this->assertSame('0',$session->read($id));
        $this->travel(121)->seconds();
        $this->assertSame('',$another->read($id));
        $this->assertSame(1,$session->gc(120));
        $this->assertSame(0,$this->documents->count('runtime_sessions'));
        $this->assertFalse($session->write('../admins/owner','invalid'));
        $this->assertSame('',$session->read('../admins/owner'));
        $this->assertFalse($session->destroy('../admins/owner'));
    }

    public function test_session_gc_does_not_delete_a_session_renewed_after_its_query(): void
    {
        $session=new FirestoreSessionHandler($this->documents,1,'school-session');
        $id=str_repeat('b',40);
        $session->write($id,'original');
        $this->travel(61)->seconds();
        $this->documents->onDelete = fn () => $session->write($id,'renewed');
        $this->assertSame(0,$session->gc(60));
        $this->assertSame('renewed',$session->read($id));
    }

    public function test_drivers_are_registered_without_local_database_or_file_sessions(): void
    {
        $this->app->instance(FirestoreDocumentStore::class,$this->documents);
        $cache=Cache::store('firestore');
        $this->assertInstanceOf(FirestoreCacheStore::class,$cache->getStore());
        $cache->put('driver-test','works',60);
        $this->assertSame('works',$cache->get('driver-test'));
        $manager=$this->app->make('session');
        $this->assertInstanceOf(FirestoreSessionHandler::class,$manager->driver('firestore')->getHandler());
        Http::assertNothingSent();
    }
}

class MemoryFirestore extends FirestoreDocumentStore
{
    private array $data=[];
    private int $version=0;
    public ?\Closure $onCompare=null;
    public ?\Closure $onDelete=null;
    public function get(string $collection,string $id): ?array { return $this->data[$collection][$id]['data'] ?? null; }
    public function put(string $collection,string $id,array $data): void
    {
        $this->data[$collection][$id]=['data'=>['id'=>$id]+$data,'version'=>(string)++$this->version];
    }
    public function create(string $collection,string $id,array $data): bool
    {
        if ($this->get($collection,$id)) return false;
        $this->put($collection,$id,$data);return true;
    }
    public function delete(string $collection,string $id): void { unset($this->data[$collection][$id]); }
    public function snapshot(string $collection,string $id): array { return $this->data[$collection][$id] ?? ['data'=>null,'version'=>null]; }
    public function compareAndSwap(string $collection,string $id,?string $version,array $data): bool
    {
        if ($callback=$this->onCompare) { $this->onCompare=null;$callback($collection,$id); }
        if ($this->snapshot($collection,$id)['version']!==$version) return false;
        $this->put($collection,$id,$data);return true;
    }
    public function deleteIfUnchanged(string $collection,string $id,string $version): bool
    {
        if ($callback=$this->onDelete) { $this->onDelete=null;$callback($collection,$id); }
        if ($this->snapshot($collection,$id)['version']!==$version) return false;
        $this->delete($collection,$id);return true;
    }
    public function page(string $collection,?string $cursor=null,int $limit=25): array
    {
        $items=array_values($this->data[$collection] ?? []);
        return ['items'=>array_map(fn($row)=>$row['data'],$items),'next'=>null];
    }
    public function count(string $collection): int { return count($this->data[$collection] ?? []); }
    public function expiredSnapshots(string $collection,int $timestamp,int $limit=100): array
    {
        return array_values(array_filter($this->data[$collection] ?? [],fn($row)=>$row['data']['expires_at']>0 && $row['data']['expires_at']<=$timestamp));
    }
}
