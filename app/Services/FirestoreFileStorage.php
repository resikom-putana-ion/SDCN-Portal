<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class FirestoreFileStorage
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const CHUNK_SIZE = 512 * 1024;
    private const FILES = 'uploaded_files';
    private const CHUNKS = 'uploaded_file_chunks';

    public function __construct(private FirestoreDocumentStore $documents) {}

    public function putUploadedFile(string $path, UploadedFile $file): array
    {
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Ukuran berkas maksimum 5 MB.');
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Berkas unggahan tidak dapat dibaca.');
        }

        return $this->put($path, $contents, $file->getMimeType() ?: 'application/octet-stream');
    }

    public function put(string $path, string $contents, string $contentType = 'application/octet-stream'): array
    {
        $path = $this->path($path);
        $contentType = $this->contentType($contentType);
        $size = strlen($contents);

        if ($size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Ukuran berkas maksimum 5 MB.');
        }

        $fileId = hash('sha256', $path);
        $previous = $this->documents->get(self::FILES, $fileId);
        $chunks = str_split($contents, self::CHUNK_SIZE);
        $chunkDocuments = [];

        foreach ($chunks as $index => $chunk) {
            $chunkDocuments[$this->chunkId($path, $index)] = [
                'file_id' => $fileId,
                'index' => $index,
                'data' => base64_encode($chunk),
            ];
        }

        $metadata = [
            'path' => $path,
            'content_type' => $contentType,
            'size' => $size,
            'chunk_count' => count($chunks),
            'created_at' => now()->toIso8601String(),
        ];

        $this->documents->putDocuments([
            self::CHUNKS => $chunkDocuments,
            self::FILES => [$fileId => $metadata],
        ]);

        $previousChunkCount = (int) ($previous['chunk_count'] ?? 0);
        if ($previousChunkCount > count($chunks)) {
            $staleIds = [];
            for ($index = count($chunks); $index < $previousChunkCount; $index++) {
                $staleIds[] = $this->chunkId($path, $index);
            }
            $this->documents->deleteMany(self::CHUNKS, $staleIds);
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'size' => $size,
            'content_type' => $contentType,
        ];
    }

    public function exists(string $path): bool
    {
        return $this->documents->get(self::FILES, hash('sha256', $this->path($path))) !== null;
    }

    /**
     * @return array{contents: string, content_type: string, size: int}|null
     */
    public function get(string $path): ?array
    {
        $path = $this->path($path);
        $fileId = hash('sha256', $path);
        $metadata = $this->documents->get(self::FILES, $fileId);

        if ($metadata === null) {
            return null;
        }

        $size = $metadata['size'] ?? null;
        $chunkCount = $metadata['chunk_count'] ?? null;
        if (($metadata['path'] ?? null) !== $path || ! is_int($size) || $size < 1 || $size > self::MAX_FILE_SIZE
            || ! is_int($chunkCount) || $chunkCount < 1 || $chunkCount > 10) {
            throw new RuntimeException('Metadata berkas Firestore tidak valid.');
        }
        if (! is_string($metadata['content_type'] ?? null)) {
            throw new RuntimeException('Content-Type berkas Firestore tidak valid.');
        }

        $contents = '';
        for ($index = 0; $index < $chunkCount; $index++) {
            $chunk = $this->documents->get(self::CHUNKS, $this->chunkId($path, $index));
            $decoded = is_string($chunk['data'] ?? null) ? base64_decode($chunk['data'], true) : false;
            if ($decoded === false || ($chunk['file_id'] ?? null) !== $fileId || ($chunk['index'] ?? null) !== $index) {
                throw new RuntimeException('Berkas Firestore tidak lengkap atau rusak.');
            }
            $contents .= $decoded;
        }

        if (strlen($contents) !== $size) {
            throw new RuntimeException('Ukuran berkas Firestore tidak sesuai metadata.');
        }

        return [
            'contents' => $contents,
            'content_type' => $this->contentType($metadata['content_type']),
            'size' => $size,
        ];
    }

    public function delete(string $path): bool
    {
        $path = $this->path($path);
        $fileId = hash('sha256', $path);
        $metadata = $this->documents->get(self::FILES, $fileId);

        if ($metadata === null) {
            return false;
        }

        $chunkCount = $metadata['chunk_count'] ?? null;
        if (! is_int($chunkCount) || $chunkCount < 1 || $chunkCount > 10) {
            throw new RuntimeException('Metadata berkas Firestore tidak valid.');
        }

        $chunkIds = [];
        for ($index = 0; $index < $chunkCount; $index++) {
            $chunkIds[] = $this->chunkId($path, $index);
        }
        $this->documents->deleteDocuments([
            self::CHUNKS => $chunkIds,
            self::FILES => [$fileId],
        ]);

        return true;
    }

    private function chunkId(string $path, int $index): string
    {
        return hash('sha256', $path.':'.$index);
    }

    private function path(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '' || strlen($path) > 1024 || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \InvalidArgumentException('Path berkas tidak valid.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Path berkas tidak valid.');
            }
        }

        return $path;
    }

    private function contentType(string $contentType): string
    {
        $contentType = trim($contentType);
        if ($contentType === '' || strlen($contentType) > 255 || preg_match('/[\r\n]/', $contentType)) {
            throw new \InvalidArgumentException('Content-Type berkas tidak valid.');
        }

        return $contentType;
    }
}
