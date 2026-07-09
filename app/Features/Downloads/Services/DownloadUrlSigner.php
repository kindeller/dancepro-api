<?php

namespace App\Features\Downloads\Services;

use App\Features\Downloads\Models\DownloadLink;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DownloadUrlSigner
{
    public function signedUrl(DownloadLink $downloadLink): string
    {
        $expiresAt = now()->addMinutes((int) config('downloads.signed_url_ttl_minutes', 5));

        if ($this->hasCloudFrontConfiguration()) {
            return $this->cloudFrontSignedUrl($downloadLink, $expiresAt->getTimestamp());
        }

        return Storage::disk($downloadLink->storage_disk)->temporaryUrl(
            $downloadLink->storage_key,
            $expiresAt,
            [
                'ResponseContentDisposition' => $this->contentDisposition($downloadLink),
            ],
        );
    }

    private function hasCloudFrontConfiguration(): bool
    {
        return filled(config('downloads.cloudfront.domain'))
            && filled(config('downloads.cloudfront.key_pair_id'))
            && filled($this->privateKey());
    }

    private function cloudFrontSignedUrl(DownloadLink $downloadLink, int $expiresAt): string
    {
        $resourceUrl = sprintf(
            'https://%s/%s',
            trim((string) config('downloads.cloudfront.domain'), '/'),
            implode('/', array_map('rawurlencode', explode('/', $downloadLink->storage_key))),
        );

        $policy = json_encode([
            'Statement' => [[
                'Resource' => $resourceUrl,
                'Condition' => [
                    'DateLessThan' => ['AWS:EpochTime' => $expiresAt],
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        $signature = '';
        $privateKey = openssl_pkey_get_private($this->privateKey());

        if ($policy === false || $privateKey === false || openssl_sign($policy, $signature, $privateKey, OPENSSL_ALGO_SHA1) === false) {
            throw new RuntimeException('Unable to sign download URL.');
        }

        return $resourceUrl.'?'.http_build_query([
            'Expires' => $expiresAt,
            'Signature' => $this->cloudFrontEncode($signature),
            'Key-Pair-Id' => config('downloads.cloudfront.key_pair_id'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function contentDisposition(DownloadLink $downloadLink): string
    {
        $filename = $downloadLink->original_filename ?: basename($downloadLink->storage_key);
        $fallback = str_replace(['"', '\\', "\r", "\n"], '', $filename);

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $fallback,
            rawurlencode($filename),
        );
    }

    private function privateKey(): ?string
    {
        $privateKey = config('downloads.cloudfront.private_key');

        if (filled($privateKey)) {
            return str_replace('\n', "\n", (string) $privateKey);
        }

        $path = config('downloads.cloudfront.private_key_path');

        if (! filled($path) || ! is_readable($path)) {
            return null;
        }

        return file_get_contents($path) ?: null;
    }

    private function cloudFrontEncode(string $value): string
    {
        return strtr(base64_encode($value), [
            '+' => '-',
            '=' => '_',
            '/' => '~',
        ]);
    }
}
