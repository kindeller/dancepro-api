<?php

namespace App\Shared\Support;

class LocalMediaUrl
{
    public function display(?string $url): ?string
    {
        if ($url === null || ! app()->environment('local')) {
            return $url;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $localHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && is_string($localHost) && strcasecmp($host, $localHost) === 0
            ? $url
            : null;
    }
}
