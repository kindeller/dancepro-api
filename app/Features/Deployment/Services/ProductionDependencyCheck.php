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
}
