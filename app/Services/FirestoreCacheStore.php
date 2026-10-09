<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use RuntimeException;

class FirestoreCacheStore implements Store, LockProvider
{
    public function __construct(
        private FirestoreDocumentStore $documents,
        private string $prefix = '',
        private string $collection = 'runtime_cache',
    ) {
        if (!in_array($collection, ['runtime_cache', 'runtime_locks'], true)) throw new \InvalidArgumentException('Invalid runtime cache collection.');
    }

    private function id(string $key): string { return hash('sha256', $this->prefix."\0".$key); }
    private function namespace(): string { return hash('sha256', $this->prefix); }
    private function expired(?array $record): bool
    {
        return !$record || (($record['expires_at'] ?? 0) !== 0 && $record['expires_at'] <= now()->timestamp);
    }
    private function record(mixed $value, int $expires): array
    {
        return ['namespace'=>$this->namespace(), 'payload'=>base64_encode(serialize($value)), 'expires_at'=>$expires];
    }
    private function value(array $record): mixed
    {
        $serialized = base64_decode($record['payload'], true);
        if ($serialized === false) throw new RuntimeException('Invalid Firestore cache payload.');
        // The collection is writable only by the trusted Laravel service account.
        return unserialize($serialized);
    }
    public function get($key): mixed
    {
        $record = $this->documents->get($this->collection, $this->id($key));
        return $this->expired($record) ? null : $this->value($record);
    }
    public function many(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) $values[$key] = $this->get($key);
        return $values;
    }
    public function put($key, $value, $seconds): bool
    {
        if ($seconds <= 0) return $this->forget($key);
        $this->documents->put($this->collection, $this->id($key), $this->record($value, now()->timestamp + (int) $seconds));
        return true;
    }
    public function putMany(array $values, $seconds): bool
    {
        foreach ($values as $key=>$value) $this->put($key, $value, $seconds);
        return true;
    }
    public function forever($key, $value): bool
    {
        $this->documents->put($this->collection, $this->id($key), $this->record($value, 0));
        return true;
    }
    public function forget($key): bool
    {
        $this->documents->delete($this->collection, $this->id($key));
        return true;
    }
    public function add($key, $value, $seconds): bool
    {
        if ($seconds <= 0) return false;
        $id = $this->id($key);
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $snapshot = $this->documents->snapshot($this->collection, $id);
            if (!$this->expired($snapshot['data'])) return false;
            if ($this->documents->compareAndSwap($this->collection, $id, $snapshot['version'],
                $this->record($value, now()->timestamp + (int) $seconds))) return true;
        }
        throw new RuntimeException('Firestore cache remained busy while adding a key.');
    }
    public function increment($key, $value = 1): int|false
    {
        $id = $this->id($key);
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $snapshot = $this->documents->snapshot($this->collection, $id);
            if ($this->expired($snapshot['data'])) return false;
            $previous = $this->value($snapshot['data']);
            if (!is_numeric($previous) || !is_numeric($value)) return false;
            $next = (int) ($previous + $value);
            if ($this->documents->compareAndSwap($this->collection, $id, $snapshot['version'],
                $this->record($next, $snapshot['data']['expires_at']))) return $next;
        }
        throw new RuntimeException('Firestore cache remained busy while incrementing a key.');
    }
    public function decrement($key, $value = 1): int|false { return $this->increment($key, -$value); }
    public function getPrefix(): string { return $this->prefix; }

    public function flush(): bool
    {
        $cursor = null;
        do {
            $page = $this->documents->page($this->collection, $cursor, 100);
            foreach ($page['items'] as $record) {
                if (($record['namespace'] ?? null) === $this->namespace()) $this->documents->delete($this->collection, $record['id']);
            }
            $cursor = $page['next'];
        } while ($cursor);
        return true;
    }

    public function pruneExpired(): int
    {
        $deleted = 0;
        foreach ($this->documents->expiredSnapshots($this->collection, now()->timestamp) as $snapshot) {
            if (($snapshot['data']['namespace'] ?? null) === $this->namespace()) {
                $deleted += (int) $this->documents->deleteIfUnchanged($this->collection, $snapshot['data']['id'], $snapshot['version']);
            }
        }
        return $deleted;
    }

    public function lock($name, $seconds = 0, $owner = null): FirestoreCacheLock
    {
        return new FirestoreCacheLock(new self($this->documents, $this->prefix, 'runtime_locks'), $name, $seconds, $owner);
    }
    public function restoreLock($name, $owner): FirestoreCacheLock { return $this->lock($name, 0, $owner); }

    public function acquireLock(string $name, string $owner, int $seconds): bool
    {
        // Zero means no expiry, matching Laravel's lock contract.
        $id = $this->id($name);
        $snapshot = $this->documents->snapshot($this->collection, $id);
        if (!$this->expired($snapshot['data'])) return false;
        return $this->documents->compareAndSwap($this->collection, $id, $snapshot['version'],
            $this->record($owner, $seconds > 0 ? now()->timestamp + $seconds : 0));
    }
    public function releaseOwnedLock(string $name, string $owner): bool
    {
        $id = $this->id($name);
        $snapshot = $this->documents->snapshot($this->collection, $id);
        if (!$snapshot['data'] || $this->value($snapshot['data']) !== $owner) return false;
        return $this->documents->deleteIfUnchanged($this->collection, $id, $snapshot['version']);
    }
}
