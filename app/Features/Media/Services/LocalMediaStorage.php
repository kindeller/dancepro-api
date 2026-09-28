<?php

namespace App\Features\Media\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Local-only equivalent of the signed S3 upload flow. Never used in production. */
class LocalMediaStorage
{
    public function enabled(string $disk): bool
    {
        return app()->environment('local')
            && $disk === 's3_concerts'
            && config('filesystems.disks.s3_concerts.driver') === 'local';
    }

    public function start(): string
    {
        return (string) Str::uuid();
    }

    public function uploadObject(string $key, string $checksum, int $size, mixed $input): void
    {
        $this->assertKey($key);
        $this->storeAndVerify($key, $checksum, $input, 'sha256', $size);
    }

    public function uploadPart(string $key, string $uploadId, int $number, string $checksum, mixed $input): string
    {
        $this->assertKey($key);
        $this->assertUploadId($uploadId);
        abort_unless($number >= 1 && $number <= 10000, 404);

        $partKey = $this->partKey($uploadId, $number);
        $this->storeAndVerify($partKey, $checksum, $input, 'crc64nvme', (int) config('media.multipart_part_size_bytes'));

        return '"'.md5_file(Storage::disk('s3_concerts')->path($partKey)).'"';
    }

    /** @param list<array{PartNumber: int, ETag: string, ChecksumCRC64NVME: string}> $parts */
    public function complete(string $key, string $uploadId, array $parts, string $checksum, int $sizeBytes): void
    {
        $this->assertKey($key);
        $this->assertUploadId($uploadId);
        $disk = Storage::disk('s3_concerts');
        $output = fopen('php://temp/maxmemory:2097152', 'w+b');
        if (! is_resource($output)) {
            throw new RuntimeException('Unable to assemble the local media upload.');
        }

        try {
            foreach ($parts as $part) {
                $partKey = $this->partKey($uploadId, $part['PartNumber']);
                abort_unless($disk->exists($partKey), 422);
                $input = $disk->readStream($partKey);
                if (! is_resource($input)) {
                    throw new RuntimeException('Unable to read a local media part.');
                }
                try {
                    if (! hash_equals($part['ETag'], '"'.md5_file($disk->path($partKey)).'"')
                        || ! hash_equals($part['ChecksumCRC64NVME'], $this->checksum($input, 'crc64nvme'))) {
                        throw ValidationException::withMessages(['parts' => 'A local media part does not match its upload receipt.']);
                    }
                    rewind($input);
                    stream_copy_to_stream($input, $output);
                } finally {
                    fclose($input);
                }
            }
            if (ftell($output) !== $sizeBytes) {
                throw ValidationException::withMessages(['upload' => 'The assembled local media size does not match.']);
            }
            rewind($output);
            if (! hash_equals($checksum, $this->checksum($output, 'crc64nvme'))) {
                throw ValidationException::withMessages(['upload' => 'The assembled local media checksum does not match.']);
            }
            rewind($output);
            $disk->put($key, $output);
        } finally {
            fclose($output);
        }

        $this->abort($uploadId);
    }

    public function abort(string $uploadId): void
    {
        $this->assertUploadId($uploadId);
        $disk = Storage::disk('s3_concerts');
        foreach ($disk->files('_multipart/'.$uploadId) as $part) {
            $disk->delete($part);
        }
        $disk->deleteDirectory('_multipart/'.$uploadId);
    }

    /** @return array{size: int, content_type: string|null, checksum_sha256: string|null, checksum_crc64nvme: string|null}|null */
    public function head(string $key): ?array
    {
        $disk = Storage::disk('s3_concerts');
        if (! $disk->exists($key)) {
            return null;
        }
        $stream = $disk->readStream($key);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to inspect local media.');
        }
        try {
            $crc = $this->checksum($stream, 'crc64nvme');
            rewind($stream);
            $sha = $this->checksum($stream, 'sha256');
        } finally {
            fclose($stream);
        }

        return [
            'size' => $disk->size($key),
            'content_type' => $disk->mimeType($key) ?: null,
            'checksum_sha256' => $sha,
            'checksum_crc64nvme' => $crc,
        ];
    }

    private function storeAndVerify(string $key, string $expected, mixed $input, string $algorithm, int $size): void
    {
        $disk = Storage::disk('s3_concerts');
        if (! is_resource($input)) {
            throw ValidationException::withMessages(['upload' => 'The upload body is missing.']);
        }
        $disk->put($key, $input);
        $actualSize = $disk->size($key);
        if ($actualSize < 1 || ($algorithm === 'sha256' && $actualSize !== $size)
            || ($algorithm === 'crc64nvme' && $actualSize > $size)) {
            $disk->delete($key);
            throw ValidationException::withMessages(['upload' => 'The local upload size is invalid.']);
        }
        $stored = $disk->readStream($key);
        if (! is_resource($stored)) {
            throw new RuntimeException('Unable to verify local media.');
        }
        try {
            $actual = $this->checksum($stored, $algorithm);
        } finally {
            fclose($stored);
        }
        if (! hash_equals($expected, $actual)) {
            $disk->delete($key);
            throw ValidationException::withMessages(['checksum' => 'The local upload checksum does not match.']);
        }
    }

    private function checksum(mixed $stream, string $algorithm): string
    {
        $crc = -1;
        $polynomial = unpack('q', hex2bin('b5c94bac29936c9a'))[1];
        $table = [];
        if ($algorithm === 'crc64nvme') {
            for ($index = 0; $index < 256; $index++) {
                $value = $index;
                for ($bit = 0; $bit < 8; $bit++) {
                    $value = ($value & 1)
                        ? ((($value >> 1) & 0x7FFFFFFFFFFFFFFF) ^ $polynomial)
                        : (($value >> 1) & 0x7FFFFFFFFFFFFFFF);
                }
                $table[$index] = $value;
            }
        }
        $sha = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                throw new RuntimeException('Unable to read local media for checksum.');
            }
            if ($algorithm === 'sha256') {
                hash_update($sha, $chunk);

                continue;
            }
            foreach (unpack('C*', $chunk) ?: [] as $byte) {
                $crc = $table[($crc ^ $byte) & 255] ^ (($crc >> 8) & 0x00FFFFFFFFFFFFFF);
            }
        }

        return $algorithm === 'sha256'
            ? base64_encode(hash_final($sha, true))
            : base64_encode(strrev(pack('P', $crc ^ -1)));
    }

    private function partKey(string $uploadId, int $number): string
    {
        return '_multipart/'.$uploadId.'/'.$number;
    }

    private function assertKey(string $key): void
    {
        abort_unless(((bool) preg_match('#^[0-9a-f-]{36}/media/[0-9a-f-]{36}/(?:original|stream|thumbnail)/[a-zA-Z0-9._/-]+$#', $key)
            || (bool) preg_match('#^concert-uploads/[0-9a-f-]{36}/[0-9a-f-]{36}/[0-9a-f-]{36}\.mp4$#', $key))
            && ! str_contains($key, '..'), 404);
    }

    private function assertUploadId(string $uploadId): void
    {
        abort_unless(Str::isUuid($uploadId), 404);
    }
}
