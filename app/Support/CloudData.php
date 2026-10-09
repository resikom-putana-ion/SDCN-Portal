<?php

namespace App\Support;

use App\Models\SchoolRecord;
use App\Services\FirestoreDocumentStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** School repositories backed only by Firestore. */
class CloudData
{
    private array $loaded = [];
    public static function store(): FirestoreDocumentStore { return app(FirestoreDocumentStore::class); }
    public static function clear(): void { app(self::class)->loaded = []; }
    public static function table(string $name): DocumentQuery
    {
        if (!in_array($name, ['school_payments', 'school_documents', 'admission_activations'], true)) throw new \InvalidArgumentException('Koleksi sekolah tidak dikenal.');
        return new DocumentQuery($name);
    }
    public static function transaction(callable $callback): mixed
    {
        if (self::store()->inTransaction()) return $callback();
        try {
            return self::store()->transaction(function () use ($callback) {
                self::clear();
                // Serialize uniqueness checks, balances, activation, and numbering.
                $lock = self::store()->get('dashboard_runtime', 'workflows');
                self::store()->put('dashboard_runtime', 'workflows', ['revision' => ($lock['revision'] ?? 0) + 1]);
                return $callback();
            });
        } finally { self::clear(); }
    }
    public static function collection(string $name): array
    {
        if (self::store()->inTransaction()) return self::store()->all($name);
        return app(self::class)->loaded[$name] ??= self::store()->all($name);
    }
    public static function rows(string $name): Collection
    {
        if ($name === 'records') return collect(self::collection('dashboard_records'))->map(fn ($row) => new SchoolRecord($row))
            ->concat(collect(self::collection('applications'))->map(fn ($row) => self::applicant($row)));
        $rows = collect(self::collection($name))->map(fn ($row) => (object) $row);
        if ($name === 'school_documents') foreach (self::collection('applications') as $application) {
            foreach ($application['documents'] ?? [] as $field => $file) $rows->push((object) [
                'id' => 'application-file-'.hash('sha256', $application['id'].'/'.$field),
                'record_id' => $application['id'], 'path' => $file['path'], 'name' => $file['name'] ?? $field,
                'mime' => $file['mime'] ?? 'application/octet-stream', 'size' => $file['size'] ?? 0,
                'created_at' => $application['created_at'] ?? null,
            ]);
        }
        return $rows;
    }
    public static function applicant(array $row): SchoolRecord
    {
        $status = match ($row['status'] ?? 'baru') { 'diterima' => 'Lolos administrasi', 'ditolak' => 'Perlu perbaikan', default => 'Pemeriksaan' };
        $extra = $row['dashboard'] ?? [];
        if (($extra['source_status'] ?? null) !== ($row['status'] ?? 'baru')) unset($extra['status']);
        return new SchoolRecord([
            'id' => $row['id'], 'kind' => 'applicants', 'code' => $row['reference'] ?? $row['id'],
            'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null,
            'data' => array_replace($extra, [
                'name' => data_get($row, 'child.child_name', ''), 'birth_date' => data_get($row, 'child.birth_date', ''),
                'gender' => data_get($row, 'child.gender', ''), 'previous_school' => data_get($row, 'child.previous_school', ''),
                'guardian' => data_get($row, 'parent.parent_name', ''), 'relationship' => data_get($row, 'parent.relationship', ''),
                'phone' => data_get($row, 'parent.phone', ''), 'email' => data_get($row, 'parent.email', ''),
                'address' => data_get($row, 'parent.address', ''), 'status' => $extra['status'] ?? $status,
                'notes' => $row['admin_notes'] ?? '', 'number' => $row['reference'] ?? $row['id'],
                'date' => substr($row['created_at'] ?? '', 0, 10), 'payment' => $extra['payment'] ?? 'Belum dicatat',
                'checks' => $extra['checks'] ?? [],
            ]),
        ]);
    }
    public static function saveRecord(SchoolRecord $record): void
    {
        self::transaction(function () use ($record) {
            $record->id ??= (string) Str::ulid();
            $record->created_at ??= now()->toIso8601String();
            $record->updated_at = now()->toIso8601String();
            if (SchoolRecord::where('code', $record->code)->get()->first(fn ($row) => (string) $row->id !== (string) $record->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'Kode sudah digunakan.']);
            }
            if ($record->kind === 'applicants') {
                $existing = self::store()->get('applications', (string) $record->id) ?? [];
                $data = $record->data;
                $status = match ($data['status'] ?? 'Pemeriksaan') {
                    'Lolos administrasi', 'Menunggu daftar ulang', 'Siap diaktifkan', 'Aktif' => 'diterima',
                    'Perlu perbaikan' => 'ditolak', default => $existing['status'] ?? 'baru',
                };
                self::store()->put('applications', (string) $record->id, array_replace($existing, [
                    'reference' => $record->code, 'status' => $status, 'admin_notes' => $data['notes'] ?? '',
                    'child' => array_replace($existing['child'] ?? [], ['child_name' => $data['name'], 'birth_date' => $data['birth_date'] ?? '', 'gender' => $data['gender'] ?? '', 'previous_school' => $data['previous_school'] ?? '']),
                    'parent' => array_replace($existing['parent'] ?? [], ['parent_name' => $data['guardian'] ?? '', 'relationship' => $data['relationship'] ?? '', 'phone' => $data['phone'] ?? '', 'email' => $data['email'] ?? '', 'address' => $data['address'] ?? '']),
                    'dashboard' => [...$data, 'source_status' => $status],
                    'created_at' => $record->created_at, 'updated_at' => $record->updated_at,
                ]));
            } else self::store()->put('dashboard_records', (string) $record->id, $record->toArray());
            self::clear();
        });
    }
    public static function insert(string $collection, array $data): string
    {
        $id = (string) ($data['id'] ?? Str::ulid());
        foreach ($data as &$value) if ($value instanceof \DateTimeInterface) $value = $value->format(DATE_ATOM);
        unset($value);
        if (!self::store()->create($collection, $id, $data)) throw new \RuntimeException('Dokumen sudah ada.');
        self::clear();
        return $id;
    }
}
