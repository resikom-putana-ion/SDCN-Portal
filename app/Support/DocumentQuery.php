<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Explicit collection filters for Firestore-backed school lists. */
class DocumentQuery
{
    private array $filters = [];
    private ?array $order = null;
    public function __construct(private string $collection = 'records') {}
    public function where(string $field, mixed $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) { $value = $operator; $operator = '='; }
        $field = str_replace('->', '.', $field);
        $this->filters[] = static function ($row) use ($field, $operator, $value) {
            $actual = data_get($row, $field);
            return match ($operator) {
                '=', '==' => $actual == $value, '!=', '<>' => $actual != $value,
                'like' => (bool) preg_match('/^'.str_replace(['%', '_'], ['.*', '.'], preg_quote($value, '/')).'$/u', (string) $actual),
                default => throw new \InvalidArgumentException('Filter tidak didukung.'),
            };
        };
        return $this;
    }
    public function whereIn(string $field, array $values): static { $this->filters[] = fn ($row) => in_array(data_get($row, str_replace('->', '.', $field)), $values); return $this; }
    public function get(): Collection
    {
        $rows = CloudData::rows($this->collection);
        foreach ($this->filters as $filter) $rows = $rows->filter($filter);
        if ($this->order) $rows = $rows->sortBy($this->order[0], SORT_REGULAR, $this->order[1] === 'desc');
        return $rows->values();
    }
    public function orderBy(string $field, string $direction = 'asc'): static { $this->order = [$field, $direction]; return $this; }
    public function orderByDesc(string $field): static { return $this->orderBy($field, 'desc'); }
    public function latest(string $field = 'created_at'): static { return $this->orderByDesc($field); }
    public function lockForUpdate(): static { if (!CloudData::store()->inTransaction()) throw new \LogicException('Pembacaan terkunci harus berada dalam transaksi Firestore.'); return $this; }
    public function first(): mixed { return $this->get()->first(); }
    public function firstOrFail(): mixed { $row = $this->first(); abort_unless($row, 404); return $row; }
    public function find(mixed $id): mixed { return (clone $this)->where('id', (string) $id)->first(); }
    public function findOrFail(mixed $id): mixed { $row = $this->find($id); abort_unless($row, 404); return $row; }
    public function count(): int { return $this->get()->count(); }
    public function exists(): bool { return $this->first() !== null; }
    public function sum(string $field): int|float { return $this->get()->sum($field); }
    public function insertGetId(array $data): string { return CloudData::insert($this->collection, $data); }
    public function insert(array $data): bool { $this->insertGetId($data); return true; }
    public function update(array $data): int
    {
        return CloudData::transaction(function () use ($data) {
            $rows = $this->get();
            foreach ($data as &$value) if ($value instanceof \DateTimeInterface) $value = $value->format(DATE_ATOM);
            unset($value);
            foreach ($rows as $row) CloudData::store()->put($this->collection, (string) $row->id, array_replace((array) $row, $data));
            CloudData::clear();
            return $rows->count();
        });
    }
}
