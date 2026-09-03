<?php

namespace App\Features\Operations\Services;

use RuntimeException;

class OperationalDocumentMetadata
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    /** @return array{file_mime_type: string, file_size: int, file_checksum: string} */
    public function forPath(string $path): array
    {
        $disk = $this->files->disk();
        $stream = $disk->readStream($path);
        if ($stream === false) {
            throw new RuntimeException("Could not read operational document: {$path}");
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
        } finally {
            fclose($stream);
        }

        return [
            'file_mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'file_size' => $disk->size($path),
            'file_checksum' => hash_final($hash),
        ];
    }
}
