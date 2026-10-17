<?php

namespace Tests\Support;

use App\Services\FirestoreDocumentStore;

class MemoryFirestore extends FirestoreDocumentStore
{
    public array $documents = [];
    private bool $active = false;
    private array $versions = [];
    public function inTransaction(): bool { return $this->active; }
    public function transaction(callable $callback): mixed
    {
        if ($this->active) return $callback();
        $before = $this->documents; $versions = $this->versions; $this->active = true;
        try { return $callback(); }
        catch (\Throwable $e) { $this->documents = $before; $this->versions = $versions; throw $e; }
        finally { $this->active = false; }
    }
    public function get(string $collection, string $id): ?array { return $this->documents[$collection][$id] ?? null; }
    public function put(string $collection, string $id, array $data): void
    {
        $this->documents[$collection][$id] = ['id' => $id] + $data;
        $this->versions[$collection][$id] = ($this->versions[$collection][$id] ?? 0) + 1;
    }
    public function create(string $collection, string $id, array $data): bool
    {
        if ($this->get($collection, $id)) return false;
        $this->put($collection, $id, $data); return true;
    }
    public function delete(string $collection, string $id): void { unset($this->documents[$collection][$id], $this->versions[$collection][$id]); }
    public function all(string $collection): array { return array_values($this->documents[$collection] ?? []); }
    public function page(string $collection, ?string $cursor = null, int $limit = 25): array
    {
        $items = $this->all($collection); $start = (int) $cursor;
        return ['items' => array_slice($items, $start, $limit), 'next' => count($items) > $start + $limit ? (string) ($start + $limit) : null];
    }
    public function count(string $collection): int { return count($this->all($collection)); }
    public function putDocuments(array $collections): void { foreach ($collections as $collection => $rows) foreach ($rows as $id => $row) $this->put($collection, (string) $id, $row); }
    public function deleteDocuments(array $collections): void { foreach ($collections as $collection => $ids) foreach ($ids as $id) $this->delete($collection, (string) $id); }
    public function snapshot(string $collection, string $id): array { return ['data' => $this->get($collection, $id), 'version' => isset($this->versions[$collection][$id]) ? (string) $this->versions[$collection][$id] : null]; }
    public function compareAndSwap(string $collection, string $id, ?string $version, array $data): bool
    {
        if ($this->snapshot($collection, $id)['version'] !== $version) return false;
        $this->put($collection, $id, $data); return true;
    }
    public function deleteIfUnchanged(string $collection, string $id, string $version): bool
    {
        if ($this->snapshot($collection, $id)['version'] !== $version) return false;
        $this->delete($collection, $id); return true;
    }
    public function expiredSnapshots(string $collection, int $timestamp, int $limit = 100): array
    {
        return array_values(array_map(fn ($row) => $this->snapshot($collection, $row['id']), array_slice(array_filter($this->all($collection), fn ($row) => ($row['expires_at'] ?? 0) > 0 && $row['expires_at'] <= $timestamp), 0, $limit)));
    }
}
