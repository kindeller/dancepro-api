<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadAccess;

class PruneDownloadAccesses
{
    public function execute(): int
    {
        $retentionDays = max(1, (int) config('downloads.access_retention_days'));

        return DownloadAccess::query()
            ->where('accessed_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
