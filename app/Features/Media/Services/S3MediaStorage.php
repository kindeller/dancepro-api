<?php

namespace App\Features\Media\Services;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class S3MediaStorage
{
    /**
     * @return array{url: string, headers: array<string, mixed>}
     */
    public function presignPut(string $disk, string $key, string $contentType, string $checksum, \DateTimeInterface $expiresAt): array
    {
        $request = Storage::disk($disk)->temporaryUploadUrl($key, $expiresAt, [
            'ContentType' => $contentType,
            'ChecksumSHA256' => $checksum,
        ]);

        return ['url' => $request['url'], 'headers' => $this->clientHeaders($request['headers'])];
    }

    /**
     * @return array{upload_id: string}
     */
    public function startMultipart(string $disk, string $key, string $contentType): array
    {
        [$adapter, $bucket, $resolvedKey] = $this->s3Context($disk, $key);
        $result = $adapter->getClient()->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $resolvedKey,
            'ContentType' => $contentType,
            'ChecksumAlgorithm' => 'CRC64NVME',
            'ChecksumType' => 'FULL_OBJECT',
        ]);

        return ['upload_id' => (string) $result['UploadId']];
    }

    /**
     * @return array{url: string, headers: array<string, mixed>}
     */
    public function presignPart(
        string $disk,
        string $key,
        string $uploadId,
        int $partNumber,
        string $checksum,
        \DateTimeInterface $expiresAt,
    ): array {
        [$adapter, $bucket, $resolvedKey] = $this->s3Context($disk, $key);
        $command = $adapter->getClient()->getCommand('UploadPart', [
            'Bucket' => $bucket,
            'Key' => $resolvedKey,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'ChecksumCRC64NVME' => $checksum,
        ]);
        $request = $adapter->getClient()->createPresignedRequest($command, $expiresAt);

        return ['url' => (string) $request->getUri(), 'headers' => $this->clientHeaders($request->getHeaders())];
    }

    /**
     * @param  list<array{PartNumber: int, ETag: string, ChecksumCRC64NVME: string}>  $parts
     */
    public function completeMultipart(
        string $disk,
        string $key,
        string $uploadId,
        array $parts,
        string $checksum,
        int $sizeBytes,
    ): void {
        [$adapter, $bucket, $resolvedKey] = $this->s3Context($disk, $key);
        $adapter->getClient()->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $resolvedKey,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
            'ChecksumCRC64NVME' => $checksum,
            'MpuObjectSize' => $sizeBytes,
        ]);
    }

    public function abortMultipart(string $disk, string $key, string $uploadId): void
    {
        [$adapter, $bucket, $resolvedKey] = $this->s3Context($disk, $key);
        $adapter->getClient()->abortMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $resolvedKey,
            'UploadId' => $uploadId,
        ]);
    }

    /**
     * @return array{size: int, content_type: string|null, checksum_sha256: string|null, checksum_crc64nvme: string|null}|null
     */
    public function head(string $disk, string $key): ?array
    {
        $filesystem = Storage::disk($disk);
        if (! $filesystem instanceof AwsS3V3Adapter) {
            if (! $filesystem->exists($key)) {
                return null;
            }

            return [
                'size' => $filesystem->size($key),
                'content_type' => $filesystem->mimeType($key) ?: null,
                'checksum_sha256' => null,
                'checksum_crc64nvme' => null,
            ];
        }

        [$adapter, $bucket, $resolvedKey] = $this->s3Context($disk, $key);
        try {
            $result = $adapter->getClient()->headObject([
                'Bucket' => $bucket,
                'Key' => $resolvedKey,
                'ChecksumMode' => 'ENABLED',
            ]);
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404 || $exception->getAwsErrorCode() === 'NotFound') {
                return null;
            }

            throw $exception;
        }

        return [
            'size' => (int) $result['ContentLength'],
            'content_type' => isset($result['ContentType']) ? (string) $result['ContentType'] : null,
            'checksum_sha256' => isset($result['ChecksumSHA256']) ? (string) $result['ChecksumSHA256'] : null,
            'checksum_crc64nvme' => isset($result['ChecksumCRC64NVME']) ? (string) $result['ChecksumCRC64NVME'] : null,
        ];
    }

    public function read(string $disk, string $key): string
    {
        $contents = Storage::disk($disk)->get($key);
        if ($contents === null) {
            throw new RuntimeException("Unable to read media object: {$key}");
        }

        return $contents;
    }

    /**
     * @return array{objects: list<array{key: string, size: int, last_modified: string|null}>, next_token: string|null}
     */
    public function listObjects(string $disk, string $prefix, int $limit, ?string $continuationToken): array
    {
        $filesystem = Storage::disk($disk);
        if (! $filesystem instanceof AwsS3V3Adapter) {
            $keys = collect($filesystem->allFiles($prefix))->sort()->values();
            $offset = $continuationToken === null ? 0 : max(0, (int) $continuationToken);
            $slice = $keys->slice($offset, $limit);

            return [
                'objects' => $slice->map(fn (string $key): array => [
                    'key' => $key,
                    'size' => $filesystem->size($key),
                    'last_modified' => null,
                ])->all(),
                'next_token' => $offset + $slice->count() < $keys->count() ? (string) ($offset + $slice->count()) : null,
            ];
        }

        [$adapter, $bucket, $resolvedPrefix] = $this->s3Context($disk, $prefix);
        $arguments = ['Bucket' => $bucket, 'Prefix' => $resolvedPrefix, 'MaxKeys' => $limit];
        if ($continuationToken !== null) {
            $arguments['ContinuationToken'] = $continuationToken;
        }
        $result = $adapter->getClient()->listObjectsV2($arguments);

        return [
            'objects' => collect($result['Contents'] ?? [])->map(fn ($object): array => [
                'key' => ltrim(substr((string) $object['Key'], strlen($filesystem->path(''))), '/'),
                'size' => (int) $object['Size'],
                'last_modified' => $object['LastModified']?->format(DATE_ATOM),
            ])->all(),
            'next_token' => isset($result['NextContinuationToken']) ? (string) $result['NextContinuationToken'] : null,
        ];
    }

    /**
     * @return array{0: AwsS3V3Adapter, 1: string, 2: string}
     */
    private function s3Context(string $disk, string $key): array
    {
        $filesystem = Storage::disk($disk);
        if (! $filesystem instanceof AwsS3V3Adapter) {
            throw new RuntimeException("The {$disk} disk is not configured as S3.");
        }

        return [$filesystem, (string) $filesystem->getConfig()['bucket'], $filesystem->path($key)];
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function clientHeaders(array $headers): array
    {
        unset($headers['Host'], $headers['host']);

        return collect($headers)->mapWithKeys(function (mixed $value, string $name): array {
            return [$name => is_array($value) ? implode(', ', $value) : (string) $value];
        })->all();
    }
}
