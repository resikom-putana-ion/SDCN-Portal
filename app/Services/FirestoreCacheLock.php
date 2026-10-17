<?php

namespace App\Services;

use Illuminate\Cache\Lock;

class FirestoreCacheLock extends Lock
{
    public function __construct(private FirestoreCacheStore $store, $name, $seconds, $owner = null)
    {
        parent::__construct($name, $seconds, $owner);
    }
    public function acquire(): bool { return $this->store->acquireLock($this->name, $this->owner, $this->seconds); }
    public function release(): bool { return $this->store->releaseOwnedLock($this->name, $this->owner); }
    protected function getCurrentOwner(): mixed { return $this->store->get($this->name); }
    public function forceRelease(): void { $this->store->forget($this->name); }
}
