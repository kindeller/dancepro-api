<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Support\ConcertPlaybackSource;
use Aws\CloudFront\CookieSigner;
use Aws\CloudFront\UrlSigner;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class ConcertCloudFrontSigner
{
    public function isConfigured(): bool
    {
        return ! app()->environment('local')
            && filled(config('concerts.playback.cloudfront.domain'))
            && filled(config('concerts.playback.cloudfront.key_pair_id'))
            && filled($this->privateKey());
    }

    public function canSignCookies(): bool
    {
        return $this->isConfigured()
            && filled(config('concerts.playback.cloudfront.cookie_domain'));
    }

    public function urlFor(string $key): string
    {
        return sprintf(
            'https://%s/%s',
            trim((string) config('concerts.playback.cloudfront.domain'), '/'),
            implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/')))),
        );
    }

    public function signedVideoUrl(ConcertPlaybackSource $source): string
    {
        if (! $this->isConfigured() || $source->disk !== 's3_concerts') {
            throw new RuntimeException('Signed CloudFront concert playback is not available for this storage disk.');
        }

        return $this->signedFileUrl($source->key);
    }

    public function signedFileUrl(string $key, ?string $downloadName = null, ?string $revision = null, ?\DateTimeInterface $expiresAt = null): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Signed CloudFront concert delivery is not configured.');
        }

        $url = $this->urlFor($key);
        $query = [];
        if ($downloadName !== null) {
            $fallback = preg_replace('/[^\x20-\x7E]/', '_', basename($downloadName));
            $disposition = 'attachment; filename="'.str_replace(['"', '\\'], '_', $fallback).'"; filename*=UTF-8\'\''.rawurlencode($downloadName);
            $query['response-content-disposition'] = $disposition;
        }
        if ($revision !== null) {
            $query['v'] = $revision;
        }
        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return (new UrlSigner(
            (string) config('concerts.playback.cloudfront.key_pair_id'),
            (string) $this->privateKey(),
        ))->getSignedUrl(
            $url,
            ($expiresAt ?? now()->addMinutes((int) config('concerts.playback.signed_url_ttl_minutes', 15)))->getTimestamp(),
        );
    }

    /**
     * @return list<Cookie>
     */
    public function cookiesFor(ConcertPlaybackSource $source, ?\DateTimeInterface $expiresAt = null): array
    {
        if (! $this->canSignCookies()) {
            throw new RuntimeException('Concert CloudFront signing is not configured.');
        }

        $expiresAt = ($expiresAt ?? now()->addMinutes((int) config('concerts.playback.signed_url_ttl_minutes', 15)))->getTimestamp();
        $policy = json_encode([
            'Statement' => [[
                'Resource' => $this->urlFor($source->assetPrefix).'/*',
                'Condition' => [
                    'DateLessThan' => ['AWS:EpochTime' => $expiresAt],
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        if ($policy === false) {
            throw new RuntimeException('Unable to create the concert playback policy.');
        }

        $values = (new CookieSigner(
            (string) config('concerts.playback.cloudfront.key_pair_id'),
            (string) $this->privateKey(),
        ))->getSignedCookie(policy: $policy);

        return collect($values)
            ->map(fn (string $value, string $name) => Cookie::create(
                name: $name,
                value: $value,
                expire: 0,
                path: (string) config('concerts.playback.cloudfront.cookie_path', '/'),
                domain: (string) config('concerts.playback.cloudfront.cookie_domain'),
                secure: (bool) config('concerts.playback.cloudfront.cookie_secure', true),
                httpOnly: true,
                raw: false,
                sameSite: (string) config('concerts.playback.cloudfront.cookie_same_site', 'lax'),
            ))
            ->values()
            ->all();
    }

    private function privateKey(): ?string
    {
        $privateKey = config('concerts.playback.cloudfront.private_key');

        if (filled($privateKey)) {
            return str_replace('\\n', "\n", (string) $privateKey);
        }

        $path = config('concerts.playback.cloudfront.private_key_path');

        if (! filled($path) || ! is_readable($path)) {
            return null;
        }

        return file_get_contents($path) ?: null;
    }
}
