<?php

namespace App\Services;

use App\Models\SchoolRecord;
use App\Support\CloudData;
use Illuminate\Support\Collection;

/** Read paths for the feed; never use the collection-scanning DocumentQuery. */
class NotificationRecords
{
    private array $byId = [];

    public function records(array $filters): Collection
    {
        return collect(CloudData::store()->query('dashboard_records', $filters))
            ->map(fn ($row) => new SchoolRecord($row));
    }

    public function applicants(): Collection
    {
        // Source status can override dashboard status after a landing-page update.
        return collect(CloudData::store()->query('applications', [['status', 'NOT_EQUAL', 'ditolak']]))
            ->map(fn ($row) => CloudData::applicant($row))
            ->concat($this->records([['kind', 'EQUAL', 'applicants']]));
    }

    public function find(mixed $id, string $kind): ?SchoolRecord
    {
        if (!$id) return null;
        if (!array_key_exists((string) $id, $this->byId)) {
            $row = CloudData::store()->get('dashboard_records', (string) $id);
            $this->byId[(string) $id] = $row ? new SchoolRecord($row) : null;
        }
        $record = $this->byId[(string) $id];
        return $record?->kind === $kind ? $record : null;
    }

    public function payments(array $filters): Collection
    {
        return collect(CloudData::store()->query('school_payments', $filters))->map(fn ($row) => (object) $row);
    }

    public function paidByInvoice(Collection $invoices): Collection
    {
        $payments = collect();
        foreach ($invoices->keys()->map(fn ($id) => (string) $id)->chunk(30) as $ids) {
            $payments = $payments->concat($this->payments([['invoice_id', 'IN', $ids->values()->all()]]));
        }
        return $payments->where('status', 'Terverifikasi')->groupBy(fn ($row) => (string) $row->invoice_id)
            ->map(fn ($rows) => (int) $rows->sum('amount'));
    }
}
