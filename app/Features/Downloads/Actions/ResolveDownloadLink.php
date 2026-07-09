<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\DownloadUrlSigner;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResolveDownloadLink
{
    public function __construct(
        private readonly DownloadUrlSigner $downloadUrlSigner,
        private readonly RecordDownloadAccess $recordDownloadAccess,
    ) {}

    public function handle(string $token, Request $request): RedirectResponse|JsonResponse
    {
        $downloadLink = DownloadLink::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $downloadLink) {
            return ApiResponse::error('Download link not found or expired.', status: 404);
        }

        if ($downloadLink->isRevoked()) {
            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'revoked');

            return ApiResponse::error('Download link is no longer available.', status: 410);
        }

        if ($downloadLink->isExpired()) {
            $downloadLink->forceFill([
                'status' => DownloadLinkStatus::EXPIRED,
            ])->save();

            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'expired');

            return ApiResponse::error('Download link is no longer available.', status: 410);
        }

        try {
            $signedUrl = $this->downloadUrlSigner->signedUrl($downloadLink);
        } catch (Throwable) {
            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'signing_failed');

            return ApiResponse::error('Download link is temporarily unavailable.', status: 503);
        }

        DB::transaction(function () use ($downloadLink, $request): void {
            $downloadLink->refresh();

            $downloadLink->forceFill([
                'first_opened_at' => $downloadLink->first_opened_at ?? now(),
                'last_opened_at' => now(),
                'download_count' => $downloadLink->download_count + 1,
            ])->save();

            $this->recordDownloadAccess->handle($downloadLink, $request, true);
        });

        return redirect()->away($signedUrl);
    }
}
