<?php

namespace Tests\Feature;

use App\Services\FirestoreDocumentStore;
use App\Services\FirestoreFileStorage;
use RuntimeException;
use Tests\TestCase;

class FirestoreFileStorageTest extends TestCase
{
    public function test_files_are_split_into_private_documents_and_reassembled(): void
    {
        $documents = new MemoryFileDocuments;
        $storage = new FirestoreFileStorage($documents);
        $contents = random_bytes(1024 * 1024);

        $metadata = $storage->put('applications/CN-TEST/akta.pdf', $contents, 'application/pdf');

        $this->assertSame(strlen($contents), $metadata['size']);
        $this->assertTrue($storage->exists('applications/CN-TEST/akta.pdf'));
        $this->assertSame([
            'contents' => $contents,
            'content_type' => 'application/pdf',
            'size' => strlen($contents),
        ], $storage->get('applications/CN-TEST/akta.pdf'));
        $this->assertSame(2, $documents->get('uploaded_files', hash('sha256', 'applications/CN-TEST/akta.pdf'))['chunk_count']);
        $this->assertCount(2, $documents->collections['uploaded_file_chunks']);
        foreach ($documents->collections['uploaded_file_chunks'] as $chunk) {
            $this->assertLessThan(1024 * 1024, strlen($chunk['data']));
        }
    }

    public function test_replacing_a_file_removes_old_chunks_and_delete_removes_all_documents(): void
    {
        $documents = new MemoryFileDocuments;
        $storage = new FirestoreFileStorage($documents);
        $path = 'media/school.png';
        $storage->put($path, str_repeat('a', 1024 * 1024), 'image/png');
        $storage->put($path, 'new-image', 'image/png');

        $this->assertCount(1, $documents->collections['uploaded_file_chunks']);
        $this->assertSame('new-image', $storage->get($path)['contents']);
        $this->assertTrue($storage->delete($path));
        $this->assertFalse($storage->delete($path));
        $this->assertSame([], $documents->collections['uploaded_file_chunks']);
        $this->assertSame([], $documents->collections['uploaded_files']);
    }

    public function test_oversized_unsafe_and_incomplete_files_fail_explicitly(): void
    {
        $documents = new MemoryFileDocuments;
        $storage = new FirestoreFileStorage($documents);

        try {
            $storage->put('applications/file.pdf', str_repeat('x', 5 * 1024 * 1024 + 1));
            $this->fail('Oversized files must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('maksimum 5 MB', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $storage->put('applications/../private.pdf', 'unsafe');
    }

    public function test_missing_chunk_is_reported_as_corrupt_file(): void
    {
        $documents = new MemoryFileDocuments;
        $storage = new FirestoreFileStorage($documents);
        $storage->put('applications/file.pdf', str_repeat('x', 600 * 1024));
        $documents->collections['uploaded_file_chunks'] = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak lengkap atau rusak');
        $storage->get('applications/file.pdf');
    }
}

class MemoryFileDocuments extends FirestoreDocumentStore
{
    public array $collections = [];

    public function get(string $collection, string $id): ?array
    {
        return $this->collections[$collection][$id] ?? null;
    }

    public function put(string $collection, string $id, array $data): void
    {
        $this->collections[$collection][$id] = $data;
    }

    public function create(string $collection, string $id, array $data): bool
    {
        if ($this->get($collection, $id) !== null) {
            return false;
        }
        $this->put($collection, $id, $data);

        return true;
    }

    public function delete(string $collection, string $id): void
    {
        unset($this->collections[$collection][$id]);
    }

    public function page(string $collection, ?string $cursor = null, int $limit = 25): array
    {
        return ['items' => [], 'next' => null];
    }

    public function count(string $collection): int
    {
        return count($this->collections[$collection] ?? []);
    }

    public function putDocuments(array $collections): void
    {
        foreach ($collections as $collection => $documents) {
            foreach ($documents as $id => $data) {
                $this->put($collection, $id, $data);
            }
        }
    }

    public function deleteDocuments(array $collections): void
    {
        foreach ($collections as $collection => $ids) {
            foreach ($ids as $id) {
                $this->delete($collection, $id);
            }
        }
    }

    public function deleteMany(string $collection, array $ids): void
    {
        $this->deleteDocuments([$collection => $ids]);
    }
}
