<?php

namespace App\Services;

use App\Contracts\DocumentStore;
use Illuminate\Support\Facades\Http;

class FirestoreDocumentStore implements DocumentStore
{
    private ?string $transactionId = null;
    private array $pending = [];
    private array $transactionCollections = [];

    public function inTransaction(): bool { return $this->transactionId !== null; }

    /** Buffer writes so every server read precedes the atomic Firestore commit. */
    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction()) return $callback();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->transactionId = $this->client()->post($this->endpoint().':beginTransaction', [
                'options' => ['readWrite' => (object) []],
            ])->throw()->json('transaction');
            $this->pending = [];
            $this->transactionCollections = [];
            try {
                $result = $callback();
                $writes = [];
                foreach ($this->pending as [$collection, $id, $data]) {
                    $writes[] = $data === null
                        ? ['delete' => $this->resourceName($collection, $id)]
                        : ['update' => ['name' => $this->resourceName($collection, $id)] + $this->fields($data)];
                }
                if (count($writes) > 500) throw new \RuntimeException('Operasi terlalu besar untuk satu transaksi (maksimal 500 dokumen).');
                $this->client()->post($this->endpoint().':commit', [
                    'writes' => $writes, 'transaction' => $this->transactionId,
                ])->throw();
                return $result;
            } catch (\Throwable $e) {
                try { $this->client()->post($this->endpoint().':rollback', ['transaction' => $this->transactionId]); } catch (\Throwable) {}
                if (!($e instanceof \Illuminate\Http\Client\RequestException)
                    || $e->response->json('error.status') !== 'ABORTED' || $attempt === 4) throw $e;
                usleep(50000 * ($attempt + 1));
            } finally {
                $this->transactionId = null;
                $this->pending = [];
                $this->transactionCollections = [];
            }
        }
        throw new \RuntimeException('Transaksi Firestore gagal.');
    }

    public function all(string $collection): array
    {
        $items = $this->transactionCollections[$collection] ?? null;
        if ($items === null) {
            $items = []; $cursor = null;
            do {
                $page = $this->page($collection, $cursor, 300);
                foreach ($page['items'] as $item) $items[$item['id']] = $item;
                $cursor = $page['next'];
            } while ($cursor);
            if ($this->inTransaction()) $this->transactionCollections[$collection] = $items;
        }
        foreach ($this->pending as [$name, $id, $data]) {
            if ($name !== $collection) continue;
            if ($data === null) unset($items[$id]);
            else $items[$id] = ['id' => $id] + $data;
        }
        return array_values($items);
    }
    private function endpoint(): string
    {
        return 'https://firestore.googleapis.com/v1/projects/'.rawurlencode(config('school.firebase_project')).'/databases/'.rawurlencode(config('school.firebase_database')).'/documents';
    }
    private function client()
    {
        return Http::withToken($this->accessToken())->acceptJson()->withOptions((new FirebaseCredentials)->httpOptions())
            ->baseUrl($this->endpoint());
    }

    protected function accessToken(): string
    {
        return (new FirebaseCredentials)->accessToken('https://www.googleapis.com/auth/datastore');
    }

    private function path(string $collection, string $id): string
    {
        return '/'.rawurlencode($collection).'/'.rawurlencode($id);
    }

    public static function encode(mixed $value): array
    {
        return match (true) {
            is_null($value) => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_array($value) && array_is_list($value) => ['arrayValue' => ['values' => array_map(self::encode(...), $value)]],
            is_array($value) => ['mapValue' => ['fields' => array_map(self::encode(...), $value)]],
            default => ['stringValue' => (string) $value],
        };
    }

    public static function decode(array $value): mixed
    {
        return match (array_key_first($value)) {
            'nullValue' => null,
            'integerValue' => (int) $value['integerValue'],
            'doubleValue' => (float) $value['doubleValue'],
            'booleanValue' => $value['booleanValue'],
            'mapValue' => array_map(self::decode(...), $value['mapValue']['fields'] ?? []),
            'arrayValue' => array_map(self::decode(...), $value['arrayValue']['values'] ?? []),
            default => reset($value),
        };
    }

    private function document(array $doc): array
    {
        return ['id' => basename($doc['name'])] + array_map(self::decode(...), $doc['fields'] ?? []);
    }

    private function fields(array $data): array
    {
        unset($data['id']);
        return ['fields' => (object) array_map(self::encode(...), $data)];
    }

    public function get(string $collection, string $id): ?array
    {
        if (array_key_exists($collection.'/'.$id, $this->pending)) {
            $data = $this->pending[$collection.'/'.$id][2];
            return $data === null ? null : ['id' => $id] + $data;
        }
        $response = $this->client()->get($this->path($collection, $id), array_filter(['transaction' => $this->transactionId]));
        return $response->status() === 404 ? null : $this->document($response->throw()->json());
    }

    public function put(string $collection, string $id, array $data): void
    {
        if ($this->inTransaction()) { $this->pending[$collection.'/'.$id] = [$collection, $id, $data]; return; }
        $this->client()->patch($this->path($collection, $id), $this->fields($data))->throw();
    }

    public function putMany(string $collection, array $documents): void
    {
        $this->putDocuments([$collection => $documents]);
    }

    public function deleteMany(string $collection, array $ids): void
    {
        $this->deleteDocuments([$collection => $ids]);
    }

    public function putDocuments(array $collections): void
    {
        if ($this->inTransaction()) {
            foreach ($collections as $collection => $documents) foreach ($documents as $id => $data) $this->put($collection, (string) $id, $data);
            return;
        }
        $writes = [];
        foreach ($collections as $collection => $documents) {
            foreach ($documents as $id => $data) {
                $writes[] = ['update' => ['name' => $this->resourceName($collection, (string) $id)] + $this->fields($data)];
            }
        }

        if (! $this->commitWrites($writes)) {
            throw new \RuntimeException('Firestore menolak batch penulisan dokumen.');
        }
    }

    public function deleteDocuments(array $collections): void
    {
        if ($this->inTransaction()) {
            foreach ($collections as $collection => $ids) foreach ($ids as $id) $this->delete($collection, (string) $id);
            return;
        }
        $writes = [];
        foreach ($collections as $collection => $ids) {
            foreach ($ids as $id) {
                $writes[] = ['delete' => $this->resourceName($collection, (string) $id)];
            }
        }

        if (! $this->commitWrites($writes)) {
            throw new \RuntimeException('Firestore menolak batch penghapusan dokumen.');
        }
    }

    public function create(string $collection, string $id, array $data): bool
    {
        if ($this->inTransaction()) {
            if ($this->get($collection, $id) !== null) return false;
            $this->put($collection, $id, $data);
            return true;
        }
        $response = $this->client()->post('/'.rawurlencode($collection).'?documentId='.rawurlencode($id), $this->fields($data));
        if ($response->status() === 409) return false;
        $response->throw();
        return true;
    }

    public function delete(string $collection, string $id): void
    {
        if ($this->inTransaction()) { $this->pending[$collection.'/'.$id] = [$collection, $id, null]; return; }
        $response = $this->client()->delete($this->path($collection, $id));
        if ($response->status() !== 404) $response->throw();
    }

    public function page(string $collection, ?string $cursor = null, int $limit = 25): array
    {
        $data = $this->client()->get('/'.rawurlencode($collection), array_filter([
            'pageSize' => $limit, 'pageToken' => $cursor, 'transaction' => $this->transactionId,
            // Record timestamps use Firestore's automatic descending field index.
            // Content has stable IDs and no timestamp; use its built-in name order.
            'orderBy' => '__name__ asc',
        ]))->throw()->json();
        return ['items' => array_map($this->document(...), $data['documents'] ?? []), 'next' => $data['nextPageToken'] ?? null];
    }

    public function count(string $collection): int
    {
        $data = $this->client()->post($this->endpoint().':runAggregationQuery', ['structuredAggregationQuery' => [
            'structuredQuery' => ['from' => [['collectionId' => $collection]]],
            'aggregations' => [['alias' => 'total', 'count' => (object) []]],
        ]])->throw()->json();
        return (int) ($data[0]['result']['aggregateFields']['total']['integerValue'] ?? 0);
    }

    /** Read the server update time alongside data for optimistic concurrency. */
    public function snapshot(string $collection, string $id): array
    {
        $response = $this->client()->get($this->path($collection, $id));
        if ($response->status() === 404) return ['data'=>null, 'version'=>null];
        $document = $response->throw()->json();
        return ['data'=>$this->document($document), 'version'=>$document['updateTime']];
    }

    /** Only commit when the document still has the version observed by the caller. */
    public function compareAndSwap(string $collection, string $id, ?string $version, array $data): bool
    {
        return $this->conditionalCommit([
            'update'=>['name'=>$this->resourceName($collection, $id)] + $this->fields($data),
            'currentDocument'=>$version === null ? ['exists'=>false] : ['updateTime'=>$version],
        ]);
    }

    public function deleteIfUnchanged(string $collection, string $id, string $version): bool
    {
        return $this->conditionalCommit(['delete'=>$this->resourceName($collection, $id),
            'currentDocument'=>['updateTime'=>$version]]);
    }

    private function resourceName(string $collection, string $id): string
    {
        return 'projects/'.config('school.firebase_project').'/databases/'.config('school.firebase_database').'/documents/'.$collection.'/'.$id;
    }

    private function conditionalCommit(array $write): bool
    {
        return $this->commitWrites([$write]);
    }

    private function commitWrites(array $writes): bool
    {
        if ($writes === []) {
            return true;
        }
        if (count($writes) > 500) {
            throw new \InvalidArgumentException('Firestore batch maksimal 500 dokumen.');
        }

        $response = $this->client()->post($this->endpoint().':commit', ['writes' => $writes]);
        if (in_array($response->json('error.status'), ['FAILED_PRECONDITION', 'ALREADY_EXISTS', 'ABORTED', 'NOT_FOUND'], true)) return false;
        $response->throw();
        return true;
    }

    /** Bounded sweep; update-time preconditions prevent deleting renewed records. */
    public function expiredSnapshots(string $collection, int $timestamp, int $limit = 100): array
    {
        $response = $this->client()->post($this->endpoint().':runQuery', ['structuredQuery'=>[
            'from'=>[['collectionId'=>$collection]],
            'where'=>['compositeFilter'=>['op'=>'AND', 'filters'=>[
                ['fieldFilter'=>['field'=>['fieldPath'=>'expires_at'], 'op'=>'GREATER_THAN', 'value'=>['integerValue'=>'0']]],
                ['fieldFilter'=>['field'=>['fieldPath'=>'expires_at'], 'op'=>'LESS_THAN_OR_EQUAL', 'value'=>['integerValue'=>(string) $timestamp]]],
            ]]],
            'orderBy'=>[['field'=>['fieldPath'=>'expires_at'], 'direction'=>'ASCENDING']],
            'limit'=>max(1, min(100, $limit)),
        ]])->throw()->json();
        $items = [];
        foreach ($response as $row) {
            if (isset($row['document'])) $items[] = ['data'=>$this->document($row['document']), 'version'=>$row['document']['updateTime']];
        }
        return $items;
    }
}
