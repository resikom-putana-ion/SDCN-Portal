<?php

namespace App\Services;

use App\Models\SchoolRecord;
use App\Models\User;
use App\Support\CloudData;
use App\Support\School;
use Illuminate\Validation\ValidationException;

class StudentDeletionService
{
    /** Keep each Firestore transaction comfortably below its 500-write limit. */
    private const WRITE_BUDGET = 340;

    private const PHASES = [
        'finance',
        'academic',
        'operational',
        'documents',
        'application_files',
        'application',
        'activation',
        'photo',
        'student',
    ];

    public function __construct(private FirestoreDocumentStore $store) {}

    public function preview(SchoolRecord $student): array
    {
        abort_unless($student->kind === 'students', 404);
        if (is_array($student->value('deletion_plan'))) {
            return $student->value('deletion_plan.summary', []);
        }

        return $this->summary($student->id, $this->verifiedApplicantId((string) $student->id, $student->value('applicant_id')));
    }

    public function begin(string $studentId, string $expectedNis, string $reason, User $actor): void
    {
        CloudData::transaction(function () use ($studentId, $expectedNis, $reason, $actor) {
            $student = $this->student($studentId);
            if (! hash_equals((string) $student['code'], trim($expectedNis))) {
                throw ValidationException::withMessages(['nis' => 'NIS tidak cocok. Ketik NIS siswa persis seperti yang ditampilkan.']);
            }

            $existing = data_get($student, 'data.deletion_plan');
            if (is_array($existing)) {
                return;
            }
            if (data_get($student, 'data.status') !== 'Nonaktif') {
                throw ValidationException::withMessages(['status' => 'Nonaktifkan siswa terlebih dahulu sebelum menghapusnya secara permanen.']);
            }

            $startedAt = now()->toIso8601String();
            $applicantId = $this->verifiedApplicantId($studentId, data_get($student, 'data.applicant_id'));
            $snapshot = $this->snapshot($student);
            $snapshot['deleted_at'] = $startedAt;
            $plan = [
                'version' => 1,
                'phase' => self::PHASES[0],
                'started_at' => $startedAt,
                'started_by' => (string) $actor->id,
                'reason' => $reason,
                'applicant_id' => $applicantId,
                'snapshot' => $snapshot,
                'summary' => $this->summary($studentId, $applicantId),
            ];
            $student['data']['deletion_plan'] = $plan;
            $student['updated_at'] = $startedAt;
            $this->store->put('dashboard_records', $studentId, $student);
            School::log('Memulai penghapusan permanen siswa '.$student['code'], 'Siswa', 'Diproses');
        });
    }

    /**
     * Continue a deletion in bounded, repeatable batches. A failed request can safely call this again.
     */
    public function continue(string $studentId, int $maxBatches = 4): bool
    {
        for ($batch = 0; $batch < max(1, $maxBatches); $batch++) {
            if ($this->advance($studentId)) {
                return true;
            }
        }

        return false;
    }

    private function advance(string $studentId): bool
    {
        return CloudData::transaction(function () use ($studentId) {
            $student = $this->store->get('dashboard_records', $studentId);
            if ($student === null) {
                return true;
            }
            abort_unless(($student['kind'] ?? null) === 'students', 404);
            $plan = data_get($student, 'data.deletion_plan');
            abort_unless(is_array($plan), 409, 'Penghapusan permanen belum dikonfirmasi.');

            $budget = self::WRITE_BUDGET;
            while ($budget > 12) {
                $phase = (string) ($plan['phase'] ?? self::PHASES[0]);
                $phaseIndex = array_search($phase, self::PHASES, true);
                abort_if($phaseIndex === false, 409, 'Tahap penghapusan siswa tidak valid.');

                if ($phase === 'finance') {
                    $complete = $this->archiveRecords($studentId, ['invoices'], $plan, $budget);
                } elseif ($phase === 'academic') {
                    $complete = $this->archiveRecords($studentId, ['grades'], $plan, $budget);
                } elseif ($phase === 'operational') {
                    $complete = $this->deleteOperationalRecords($studentId, $budget);
                } elseif ($phase === 'documents') {
                    $complete = $this->deleteDocuments($studentId, $plan['applicant_id'] ?? null, $budget);
                } elseif ($phase === 'application_files') {
                    $complete = $this->deleteApplicationFiles($studentId, $plan['applicant_id'] ?? null, $budget);
                } elseif ($phase === 'application') {
                    $applicantId = $plan['applicant_id'] ?? null;
                    if ($applicantId && $this->store->get('applications', (string) $applicantId)) {
                        $this->store->delete('applications', (string) $applicantId);
                        $budget--;
                    }
                    $complete = true;
                } elseif ($phase === 'activation') {
                    $complete = $this->deleteActivations($studentId, $budget);
                } elseif ($phase === 'photo') {
                    $path = data_get($student, 'data.photo_path');
                    if (is_string($path) && $path !== '' && ! $this->pathIsReferencedElsewhere($path, $studentId, $plan['applicant_id'] ?? null)) {
                        $cost = $this->fileWriteCost($path);
                        if ($cost > $budget) {
                            break;
                        }
                        $this->deleteFile($path);
                        $budget -= $cost;
                    }
                    $complete = true;
                } else {
                    $this->store->delete('dashboard_records', $studentId);
                    SchoolRecord::create([
                        'kind' => 'student_deletions',
                        'code' => $student['code'],
                        'data' => [
                            'original_student_id' => $studentId,
                            'name' => data_get($student, 'data.name', ''),
                            'reason' => $plan['reason'] ?? '',
                            'started_by' => $plan['started_by'] ?? '',
                            'started_at' => $plan['started_at'] ?? '',
                            'deleted_at' => now()->toIso8601String(),
                            'summary' => $plan['summary'] ?? [],
                            'status' => 'Dihapus permanen',
                        ],
                    ]);
                    School::log('Menghapus permanen siswa '.$student['code'], 'Siswa', 'Dihapus permanen');

                    return true;
                }

                if (! $complete) {
                    break;
                }
                $plan['phase'] = self::PHASES[$phaseIndex + 1];
            }

            $student['data']['deletion_plan'] = $plan;
            $student['updated_at'] = now()->toIso8601String();
            $this->store->put('dashboard_records', $studentId, $student);

            return false;
        });
    }

    private function archiveRecords(string $studentId, array $kinds, array $plan, int &$budget): bool
    {
        $records = array_values(array_filter($this->store->all('dashboard_records'), fn (array $row) =>
            in_array($row['kind'] ?? '', $kinds, true)
            && (string) data_get($row, 'data.student_id') === $studentId
        ));
        foreach ($records as $record) {
            if ($budget <= 12) {
                return false;
            }
            $data = $record['data'] ?? [];
            unset($data['student_id']);
            $data['student_snapshot'] = $plan['snapshot'];
            $data['student_archived_at'] = $plan['started_at'];
            $data['student_record_archived'] = true;
            $record['data'] = $data;
            $record['updated_at'] = now()->toIso8601String();
            $this->store->put('dashboard_records', (string) $record['id'], $record);
            $budget--;
        }

        return true;
    }

    private function deleteOperationalRecords(string $studentId, int &$budget): bool
    {
        $records = array_values(array_filter($this->store->all('dashboard_records'), fn (array $row) =>
            ! in_array($row['kind'] ?? '', ['students', 'invoices', 'grades'], true)
            && (string) data_get($row, 'data.student_id') === $studentId
        ));
        foreach ($records as $record) {
            if ($budget <= 12) {
                return false;
            }
            $this->store->delete('dashboard_records', (string) $record['id']);
            $budget--;
        }

        return true;
    }

    private function deleteDocuments(string $studentId, ?string $applicantId, int &$budget): bool
    {
        $targetIds = array_values(array_filter([$studentId, $applicantId], fn ($id) => $id !== null && $id !== ''));
        $documents = array_values(array_filter($this->store->all('school_documents'), fn (array $row) =>
            in_array((string) ($row['record_id'] ?? ''), $targetIds, true)
        ));

        foreach ($documents as $document) {
            $path = $document['path'] ?? null;
            $deleteFile = is_string($path) && $path !== '' && ! $this->pathIsReferencedElsewhere($path, $studentId, $applicantId);
            $cost = 1 + ($deleteFile ? $this->fileWriteCost($path) : 0);
            if ($budget - $cost <= 10) {
                return false;
            }
            if ($deleteFile) {
                $this->deleteFile($path);
            }
            $this->store->delete('school_documents', (string) $document['id']);
            $budget -= $cost;
        }

        return true;
    }

    private function deleteApplicationFiles(string $studentId, ?string $applicantId, int &$budget): bool
    {
        if (! $applicantId) {
            return true;
        }
        $application = $this->store->get('applications', $applicantId);
        if (! $application) {
            return true;
        }
        $documents = is_array($application['documents'] ?? null) ? $application['documents'] : [];
        $processed = [];
        foreach ($documents as $key => $document) {
            $path = is_array($document) ? ($document['path'] ?? null) : null;
            $deleteFile = is_string($path) && $path !== '' && ! $this->pathIsReferencedElsewhere($path, $studentId, $applicantId);
            $cost = $deleteFile ? $this->fileWriteCost($path) : 0;
            if ($budget - $cost <= 11) {
                break;
            }
            if ($deleteFile) {
                $this->deleteFile($path);
            }
            $budget -= $cost;
            $processed[] = $key;
        }

        if (count($processed) === count($documents)) {
            return true;
        }
        foreach ($processed as $key) {
            unset($documents[$key]);
        }
        $application['documents'] = $documents;
        $application['updated_at'] = now()->toIso8601String();
        $this->store->put('applications', $applicantId, $application);
        $budget--;

        return false;
    }

    private function deleteActivations(string $studentId, int &$budget): bool
    {
        $rows = array_values(array_filter($this->store->all('admission_activations'), fn (array $row) =>
            (string) ($row['student_id'] ?? '') === $studentId
        ));
        foreach ($rows as $row) {
            if ($budget <= 12) {
                return false;
            }
            $this->store->delete('admission_activations', (string) $row['id']);
            $budget--;
        }

        return true;
    }

    private function pathIsReferencedElsewhere(string $path, string $studentId, ?string $applicantId): bool
    {
        foreach ($this->store->all('dashboard_records') as $record) {
            if ((string) ($record['id'] ?? '') !== $studentId && $this->contains($record, $path)) {
                return true;
            }
        }
        foreach ($this->store->all('applications') as $application) {
            if ((string) ($application['id'] ?? '') !== (string) $applicantId && $this->contains($application, $path)) {
                return true;
            }
        }
        foreach ($this->store->all('school_documents') as $document) {
            if (! in_array((string) ($document['record_id'] ?? ''), array_filter([$studentId, $applicantId]), true)
                && $this->contains($document, $path)) {
                return true;
            }
        }
        foreach (['admins', 'content', 'dashboard_content_drafts'] as $collection) {
            foreach ($this->store->all($collection) as $row) {
                if ($this->contains($row, $path)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains(mixed $value, string $needle): bool
    {
        if (is_string($value)) {
            return hash_equals($value, $needle);
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $child) {
            if ($this->contains($child, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function fileWriteCost(string $path): int
    {
        $metadata = $this->store->get('uploaded_files', hash('sha256', trim($path, '/')));
        if (! $metadata) {
            return 0;
        }
        $chunks = $metadata['chunk_count'] ?? null;

        return 1 + (is_int($chunks) && $chunks >= 1 && $chunks <= 10 ? $chunks : 10);
    }

    private function deleteFile(string $path): void
    {
        $path = trim($path, '/');
        $fileId = hash('sha256', $path);
        $metadata = $this->store->get('uploaded_files', $fileId);
        if (! $metadata) {
            return;
        }
        $chunks = $metadata['chunk_count'] ?? null;
        $count = is_int($chunks) && $chunks >= 1 && $chunks <= 10 ? $chunks : 10;
        $chunkIds = [];
        for ($index = 0; $index < $count; $index++) {
            $chunkIds[] = hash('sha256', $path.':'.$index);
        }
        $this->store->deleteDocuments([
            'uploaded_file_chunks' => $chunkIds,
            'uploaded_files' => [$fileId],
        ]);
    }

    private function student(string $studentId): array
    {
        $student = $this->store->get('dashboard_records', $studentId);
        abort_unless($student && ($student['kind'] ?? null) === 'students', 404);

        return $student;
    }

    private function snapshot(array $student): array
    {
        return [
            'name' => (string) data_get($student, 'data.name', ''),
            'nis' => (string) ($student['code'] ?? data_get($student, 'data.nis', '')),
            'guardian' => (string) data_get($student, 'data.guardian', ''),
            'class' => (string) data_get($student, 'data.class', ''),
            'year' => (string) data_get($student, 'data.year', ''),
        ];
    }

    private function verifiedApplicantId(string $studentId, mixed $candidate): ?string
    {
        if (! is_string($candidate) && ! is_int($candidate)) {
            return null;
        }
        $applicantId = (string) $candidate;
        $application = $this->store->get('applications', $applicantId);
        if (! $application) {
            return null;
        }
        if ((string) data_get($application, 'dashboard.student_id') === $studentId) {
            return $applicantId;
        }
        foreach ($this->store->all('admission_activations') as $activation) {
            if ((string) ($activation['student_id'] ?? '') === $studentId
                && (string) ($activation['applicant_id'] ?? '') === $applicantId) {
                return $applicantId;
            }
        }

        return null;
    }

    private function summary(string $studentId, ?string $applicantId): array
    {
        $records = $this->store->all('dashboard_records');
        $invoices = array_values(array_filter($records, fn (array $row) => ($row['kind'] ?? null) === 'invoices' && (string) data_get($row, 'data.student_id') === $studentId));
        $grades = array_values(array_filter($records, fn (array $row) => ($row['kind'] ?? null) === 'grades' && (string) data_get($row, 'data.student_id') === $studentId));
        $operational = array_values(array_filter($records, fn (array $row) => ! in_array($row['kind'] ?? '', ['students', 'invoices', 'grades'], true) && (string) data_get($row, 'data.student_id') === $studentId));
        $targetIds = array_values(array_filter([$studentId, $applicantId]));
        $documents = array_values(array_filter($this->store->all('school_documents'), fn (array $row) => in_array((string) ($row['record_id'] ?? ''), $targetIds, true)));
        $application = $applicantId ? $this->store->get('applications', $applicantId) : null;
        $invoiceIds = array_map(fn (array $row) => (string) $row['id'], $invoices);
        $payments = array_filter($this->store->all('school_payments'), fn (array $row) => in_array((string) ($row['invoice_id'] ?? ''), $invoiceIds, true));

        return [
            'invoices' => count($invoices),
            'payments' => count($payments),
            'grades' => count($grades),
            'operational' => count($operational),
            'documents' => count($documents) + count($application['documents'] ?? []),
            'application' => $application ? 1 : 0,
        ];
    }
}
