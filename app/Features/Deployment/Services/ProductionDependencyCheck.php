<?php

namespace App\Features\Deployment\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ProductionDependencyCheck
{
    /** @return list<string> */
    public function run(): array
    {
        DB::connection()->select('select 1');
        $checks = ['database'];

        $cacheKey = 'deployment-health:'.Str::uuid();
        Cache::put($cacheKey, 'ok', 60);
        if (Cache::get($cacheKey) !== 'ok') {
            throw new RuntimeException('The configured cache failed its write/read check.');
        }
        Cache::forget($cacheKey);
        $checks[] = 'cache';

        $this->checkStorageDisk((string) config('operations.filesystem_disk'), 'Private storage');
        $checks[] = 'private storage';

        $this->checkStorageDisk((string) config('uploads.public_disk'), 'Public upload storage');
        $checks[] = 'public upload storage';

        foreach ([
            's3_competitions' => 'competition media storage',
            's3_concerts' => 'concert media storage',
            's3_concerts_legacy' => 'legacy concert media storage',
        ] as $diskName => $label) {
            $this->checkReadableStorageDisk($diskName, ucfirst($label));
            $checks[] = $label;
        }

        $this->checkCloudFrontSigning('concerts.playback.cloudfront', 'Concert CloudFront signing', requireCookieDomain: true);
        $checks[] = 'concert signing';

        if ($this->hasAnyConfiguration('downloads.cloudfront')) {
            $this->checkCloudFrontSigning('downloads.cloudfront', 'Download CloudFront signing');
            $checks[] = 'download signing';
        }

        Mail::mailer()->getSymfonyTransport();
        $checks[] = 'mail transport';

        $queue = (string) config('queue.default');
        $queueConfiguration = config("queue.connections.{$queue}");
        if (! is_array($queueConfiguration)) {
            throw new RuntimeException('The configured queue connection does not exist.');
        }
        if (($queueConfiguration['driver'] ?? null) === 'database') {
            DB::connection($queueConfiguration['connection'] ?? null)
                ->table((string) $queueConfiguration['table'])
                ->limit(1)
                ->get();
        }
        $checks[] = 'queue';

        return $checks;
    }

    private function checkStorageDisk(string $diskName, string $label): void
    {
        $disk = Storage::disk($diskName);
        $storagePath = 'deployment-health/'.Str::uuid().'.txt';
        try {
            $disk->put($storagePath, 'ok');
            if ($disk->get($storagePath) !== 'ok') {
                throw new RuntimeException("{$label} failed its write/read check.");
            }
        } finally {
            $disk->delete($storagePath);
        }
    }

    private function checkReadableStorageDisk(string $diskName, string $label): void
    {
        if (! is_array(config("filesystems.disks.{$diskName}"))) {
            throw new RuntimeException("{$label} disk [{$diskName}] is not configured.");
        }

        Storage::disk($diskName)->directories('deployment-health-probe');
        Storage::disk($diskName)->exists('deployment-health/read-access-probe');
    }

    private function checkCloudFrontSigning(
        string $configPrefix,
        string $label,
        bool $requireCookieDomain = false,
    ): void {
        $domain = config("{$configPrefix}.domain");
        $keyPairId = config("{$configPrefix}.key_pair_id");
        $cookieDomain = config("{$configPrefix}.cookie_domain");
        $privateKey = $this->privateKey($configPrefix);

        if (! filled($domain) || ! filled($keyPairId) || ! filled($privateKey)) {
            throw new RuntimeException("{$label} is incomplete.");
        }

        if ($requireCookieDomain && ! filled($cookieDomain)) {
            throw new RuntimeException("{$label} requires a cookie domain.");
        }

        if (@openssl_pkey_get_private($privateKey) === false) {
            throw new RuntimeException("{$label} private key is invalid or unreadable.");
        }
    }

    private function hasAnyConfiguration(string $configPrefix): bool
    {
        return collect(config($configPrefix, []))->contains(fn (mixed $value): bool => filled($value));
    }

    private function privateKey(string $configPrefix): ?string
    {
        $privateKey = config("{$configPrefix}.private_key");
        if (filled($privateKey)) {
            return str_replace('\\n', "\n", (string) $privateKey);
        }

        $path = config("{$configPrefix}.private_key_path");

        return filled($path) && is_readable($path)
            ? (file_get_contents($path) ?: null)
            : null;
    }
}
