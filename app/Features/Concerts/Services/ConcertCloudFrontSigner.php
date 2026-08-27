<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Support\ConcertPlaybackSource;
use Aws\CloudFront\CookieSigner;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class ConcertCloudFrontSigner
{
    public function isConfigured(): bool
    {
        return filled(config('concerts.playback.cloudfront.domain'))
            && filled(config('concerts.playback.cloudfront.key_pair_id'))
            && filled(config('concerts.playback.cloudfront.cookie_domain'))
            && filled($this->privateKey());
    }

    public function urlFor(string $key): string
    {
        return sprintf(
            'https://%s/%s',
            trim((string) config('concerts.playback.cloudfront.domain'), '/'),
            implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/')))),
        );
    }

    /**
     * @return list<Cookie>
     */
    public function cookiesFor(ConcertPlaybackSource $source): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Concert CloudFront signing is not configured.');
        }

        $expiresAt = now()->addMinutes((int) config('concerts.playback.signed_url_ttl_minutes', 15))->getTimestamp();
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
