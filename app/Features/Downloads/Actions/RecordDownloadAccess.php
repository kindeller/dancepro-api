<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadAccess;
use App\Features\Downloads\Models\DownloadLink;
use Illuminate\Http\Request;

class RecordDownloadAccess
{
    public function handle(DownloadLink $downloadLink, Request $request, bool $wasSuccessful, ?string $failureReason = null): DownloadAccess
    {
        return DownloadAccess::query()->create([
            'download_link_id' => $downloadLink->id,
            'accessed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
            'was_successful' => $wasSuccessful,
            'failure_reason' => $failureReason,
        ]);
    }
}
