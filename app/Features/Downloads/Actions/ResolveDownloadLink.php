<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\DownloadUrlSigner;
use App\Features\Downloads\Support\DownloadLinkStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResolveDownloadLink
{
    public function __construct(
        private readonly DownloadUrlSigner $downloadUrlSigner,
        private readonly RecordDownloadAccess $recordDownloadAccess,
    ) {}

    public function handle(string $token, Request $request): RedirectResponse|Response|StreamedResponse
    {
        $downloadLink = DownloadLink::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $downloadLink) {
            return $this->unavailable(
                'Incorrect download link',
                'This link does not exist. Check that the complete link was copied correctly.',
                404,
            );
        }

        if ($downloadLink->isRevoked()) {
            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'revoked');

            return $this->unavailable(
                'Download unavailable',
                'This download link is no longer available.',
                410,
                $downloadLink->original_filename,
            );
        }

        if ($downloadLink->isExpired()) {
            $downloadLink->forceFill([
                'status' => DownloadLinkStatus::EXPIRED,
            ])->save();

            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'expired');

            return $this->unavailable(
                'Download expired',
                'This download link has expired.',
                410,
                $downloadLink->original_filename,
                $downloadLink->expires_at?->toISOString(),
            );
        }

        try {
            $delivery = app()->environment('local')
                ? $this->localDownload($downloadLink)
                : $this->downloadUrlSigner->signedUrl($downloadLink);
        } catch (Throwable) {
            $this->recordDownloadAccess->handle($downloadLink, $request, false, 'signing_failed');

            return $this->unavailable(
                'Download temporarily unavailable',
                'This download is temporarily unavailable. Please try again later.',
                503,
                $downloadLink->original_filename,
            );
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

        return $delivery instanceof StreamedResponse
            ? $delivery
            : redirect()->away($delivery);
    }

    private function localDownload(DownloadLink $downloadLink): StreamedResponse
    {
        $disk = Storage::disk($downloadLink->storage_disk);
        abort_unless($disk->exists($downloadLink->storage_key), 404);

        return $disk->download(
            $downloadLink->storage_key,
            $downloadLink->original_filename ?: basename($downloadLink->storage_key),
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function unavailable(
        string $heading,
        string $message,
        int $status,
        ?string $filename = null,
        ?string $expiresAt = null,
    ): Response {
        return response()->view('downloads.unavailable', [
            'heading' => $heading,
            'message' => $message,
            'filename' => $filename,
            'expiresAt' => $expiresAt,
        ], $status);
    }
}
