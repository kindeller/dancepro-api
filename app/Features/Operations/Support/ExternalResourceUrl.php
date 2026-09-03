<?php

namespace App\Features\Operations\Support;

class ExternalResourceUrl
{
    public static function isAllowed(?string $url): bool
    {
        if (! filled($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $allowedHosts = config('operations.external_resource_hosts', []);

        return $allowedHosts === [] || collect($allowedHosts)->contains(
            fn (string $allowedHost): bool => $host === strtolower($allowedHost)
                || str_ends_with($host, '.'.strtolower($allowedHost))
        );
    }
}
