<?php

namespace App\Models;

use App\Support\CloudData;
use App\Support\DocumentQuery;
use Illuminate\Support\Fluent;

class SchoolRecord extends Fluent
{
    public function __get($key) { return $this->attributes[$key] ?? null; }
    public function offsetGet($key): mixed { return $this->attributes[$key] ?? null; }
    public static function query(): DocumentQuery { return new DocumentQuery; }
    public static function ofKind(string $kind): DocumentQuery { return self::query()->where('kind', $kind); }
    public static function __callStatic($method, $arguments) { return self::query()->$method(...$arguments); }
    public static function create(array $data): static { $record = new static($data); $record->save(); return $record; }
    public function save(): bool { CloudData::saveRecord($this); return true; }
    public function update(array $data): bool { $this->fill($data); return $this->save(); }
    public function fresh(): ?static { CloudData::clear(); return self::query()->find($this->id); }

    public function value($key, $default = ''): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
