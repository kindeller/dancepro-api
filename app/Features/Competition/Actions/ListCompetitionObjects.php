<?php

namespace App\Features\Competition\Actions;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListCompetitionObjects
{
    private const DISK = 's3_competitions';

    private const DEFAULT_LIMIT = 100;

    /**
     * @return array{
     *     disk: string,
     *     prefix: string,
     *     breadcrumbs: list<array{name: string, prefix: string}>,
     *     directories: list<array{type: string, name: string, prefix: string}>,
     *     files: list<array{type: string, name: string, key: string, extension: string|null, size: int|null, last_modified: string|null, console_url: string|null}>,
     *     pagination: array{limit: int, next_token: string|null, has_more: bool}
     * }
     */
    public function handle(?string $prefix, ?int $limit = null, ?string $continuationToken = null): array
    {
        $prefix = $this->normalizePrefix($prefix);
        $disk = Storage::disk(self::DISK);
        $limit = $this->normalizeLimit($limit);

        if ($disk instanceof AwsS3V3Adapter) {
            return $this->listS3Objects($disk, $prefix, $limit, $continuationToken);
        }

        $directories = collect($disk->directories($prefix))
            ->map(fn (string $directory): array => [
                'type' => 'directory',
                'name' => basename($directory),
                'prefix' => $directory,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $files = collect($disk->files($prefix))
            ->map(fn (string $file): array => [
                'type' => 'file',
                'name' => basename($file),
                'key' => $file,
                'extension' => pathinfo($file, PATHINFO_EXTENSION) ?: null,
                'size' => $this->size($file),
                'last_modified' => $this->lastModified($file),
                'console_url' => $this->consoleUrl($file),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'disk' => self::DISK,
            'prefix' => $prefix,
            'breadcrumbs' => $this->breadcrumbs($prefix),
            'directories' => $directories,
            'files' => $files,
            'pagination' => [
                'limit' => $limit,
                'next_token' => null,
                'has_more' => false,
            ],
        ];
    }

    /**
     * @return array{
     *     disk: string,
     *     prefix: string,
     *     breadcrumbs: list<array{name: string, prefix: string}>,
     *     directories: list<array{type: string, name: string, prefix: string}>,
     *     files: list<array{type: string, name: string, key: string, extension: string|null, size: int|null, last_modified: string|null, console_url: string|null}>,
     *     pagination: array{limit: int, next_token: string|null, has_more: bool}
     * }
     */
    private function listS3Objects(
        AwsS3V3Adapter $disk,
        string $prefix,
        int $limit,
        ?string $continuationToken,
    ): array {
        $config = $disk->getConfig();
        $client = new S3Client($config);
        $response = $client->listObjectsV2(array_filter([
            'Bucket' => $config['bucket'],
            'Delimiter' => '/',
            'MaxKeys' => $limit,
            'Prefix' => $this->s3Prefix($prefix),
            'ContinuationToken' => $continuationToken,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        $directories = collect($response['CommonPrefixes'] ?? [])
            ->map(function (array $item): array {
                $prefix = trim((string) ($item['Prefix'] ?? ''), '/');

                return [
                    'type' => 'directory',
                    'name' => basename($prefix),
                    'prefix' => $prefix,
                ];
            })
            ->filter(fn (array $directory): bool => $directory['prefix'] !== '')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $files = collect($response['Contents'] ?? [])
            ->map(function (array $item): array {
                $key = (string) ($item['Key'] ?? '');

                return [
                    'type' => 'file',
                    'name' => basename($key),
                    'key' => $key,
                    'extension' => pathinfo($key, PATHINFO_EXTENSION) ?: null,
                    'size' => isset($item['Size']) ? (int) $item['Size'] : null,
                    'last_modified' => isset($item['LastModified']) ? $item['LastModified']->format(DATE_ATOM) : null,
                    'console_url' => $this->consoleUrl($key),
                ];
            })
            ->filter(fn (array $file): bool => $file['key'] !== '' && $file['name'] !== '')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'disk' => self::DISK,
            'prefix' => $prefix,
            'breadcrumbs' => $this->breadcrumbs($prefix),
            'directories' => $directories,
            'files' => $files,
            'pagination' => [
                'limit' => $limit,
                'next_token' => $response['NextContinuationToken'] ?? null,
                'has_more' => (bool) ($response['IsTruncated'] ?? false),
            ],
        ];
    }

    private function normalizePrefix(?string $prefix): string
    {
        $prefix = trim(str_replace('\\', '/', (string) $prefix));

        while (str_contains($prefix, '//')) {
            $prefix = str_replace('//', '/', $prefix);
        }

        return trim($prefix, '/');
    }

    private function normalizeLimit(?int $limit): int
    {
        if ($limit === null || $limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, 1000);
    }

    private function s3Prefix(string $prefix): string
    {
        if ($prefix === '') {
            return '';
        }

        return $prefix.'/';
    }

    /**
     * @return list<array{name: string, prefix: string}>
     */
    private function breadcrumbs(string $prefix): array
    {
        if ($prefix === '') {
            return [];
        }

        $parts = explode('/', $prefix);
        $breadcrumbs = [];
        $current = '';

        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current.'/'.$part;
            $breadcrumbs[] = [
                'name' => $part,
                'prefix' => $current,
            ];
        }

        return $breadcrumbs;
    }

    private function size(string $file): ?int
    {
        try {
            return Storage::disk(self::DISK)->size($file);
        } catch (Throwable) {
            return null;
        }
    }

    private function lastModified(string $file): ?string
    {
        try {
            return now()
                ->setTimestamp(Storage::disk(self::DISK)->lastModified($file))
                ->toISOString();
        } catch (Throwable) {
            return null;
        }
    }

    private function consoleUrl(string $key): ?string
    {
        $bucket = config('filesystems.disks.'.self::DISK.'.bucket');
        $region = config('filesystems.disks.'.self::DISK.'.region');

        if (! is_string($bucket) || $bucket === '' || ! is_string($region) || $region === '') {
            return null;
        }

        return sprintf(
            'https://%s.console.aws.amazon.com/s3/buckets/%s?%s',
            rawurlencode($region),
            rawurlencode($bucket),
            http_build_query([
                'region' => $region,
                'prefix' => $key,
                'showversions' => 'false',
            ], encoding_type: PHP_QUERY_RFC3986),
        );
    }
}
