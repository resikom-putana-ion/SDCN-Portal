<?php

namespace App\Services;

use SessionHandlerInterface;

class FirestoreSessionHandler implements SessionHandlerInterface
{
    public function __construct(private FirestoreDocumentStore $documents, private int $minutes, private string $namespace) {}
    private function valid(string $id): bool { return (bool) preg_match('/^[a-zA-Z0-9,-]{20,128}$/D', $id); }
    private function id(string $id): string { return hash('sha256', $this->namespace.':'.$id); }
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        if (!$this->valid($id)) return '';
        $record = $this->documents->get('runtime_sessions', $this->id($id));
        if (!$record || ($record['expires_at'] ?? 0) <= now()->timestamp) return '';
        $payload = base64_decode($record['payload'], true);
        return $payload === false ? '' : $payload;
    }
    public function write(string $id, string $data): bool
    {
        if (!$this->valid($id)) return false;
        $this->documents->put('runtime_sessions', $this->id($id), [
            'namespace'=>hash('sha256', $this->namespace),
            'payload'=>base64_encode($data), 'expires_at'=>now()->timestamp + $this->minutes * 60,
        ]);
        return true;
    }
    public function destroy(string $id): bool
    {
        if (!$this->valid($id)) return false;
        $this->documents->delete('runtime_sessions', $this->id($id));
        return true;
    }
    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        foreach ($this->documents->expiredSnapshots('runtime_sessions', now()->timestamp) as $snapshot) {
            if (($snapshot['data']['namespace'] ?? null) === hash('sha256', $this->namespace)) {
                $deleted += (int) $this->documents->deleteIfUnchanged('runtime_sessions', $snapshot['data']['id'], $snapshot['version']);
            }
        }
        return $deleted;
    }
}
