<?php

namespace App\Features\Concerts\Services;

use App\Features\Concerts\Actions\ResolveConcertPlaybackSource;
use App\Features\Concerts\Models\ConcertFamilyDownload;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Models\ConcertFamilyVideo;
use App\Features\Media\Models\MediaAsset;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConcertFamilyDelivery
{
    public function __construct(private readonly ConcertFamilyAccess $access, private readonly ConcertStoredFiles $stored,
        private readonly ConcertCloudFrontSigner $cloudFront, private readonly ResolveConcertPlaybackSource $resolve,
        private readonly ConcertDownloadFilename $filenames) {}

    public function playback(Request $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        $video = $this->access->video($request, $share, $asset, true);
        $this->lease($share, $request->string('player')->toString());
        $expiry = $this->access->end($share, true)->min(now()->addMinutes(15));
        $localUrl = route('concert-family.media', ['share' => $share, 'asset' => $asset, 'player' => $request->string('player')->toString()]);
        $source = $this->resolve->execute($asset, $asset->storage_disk !== 's3_concerts_legacy' && $this->cloudFront->canSignCookies());
        if ($this->stored->isLocal($asset->storage_disk)) {
            $data = ['format' => 'progressive', 'url' => $localUrl, 'fallback_url' => null];
        } elseif ($asset->storage_disk === 's3_concerts_legacy') {
            $data = ['format' => 'progressive', 'url' => $this->stored->temporaryUrl($asset->storage_disk, $asset->storage_key, null, $expiry), 'fallback_url' => null];
        } else {
            $data = ['format' => $source->format->value, 'url' => $source->isHls() ? $this->cloudFront->urlFor($source->key)
                : $this->cloudFront->signedFileUrl($source->key, expiresAt: $expiry), 'fallback_url' => $source->isHls() ? $localUrl : null];
        }
        $video->increment('playback_starts');
        $video->update(['last_played_at' => now()]);
        $share->update(['last_activity_at' => now()]);
        $response = ApiResponse::success('Family playback ready.', $data)->header('Cache-Control', 'private, no-store');
        if ($source->isHls()) {
            foreach ($this->cloudFront->cookiesFor($source, $expiry) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }

    public function media(Request $request, ConcertFamilyShare $share, MediaAsset $asset)
    {
        $this->access->video($request, $share, $asset, true);
        $this->lease($share, $request->string('player')->toString());
        $expiry = $this->access->end($share, true)->min(now()->addMinutes(15));
        if ($asset->storage_disk === 's3_concerts_legacy' || $this->stored->isLocal($asset->storage_disk)) {
            return $this->stored->response($asset, expiresAt: $expiry);
        }
        $source = $this->resolve->execute($asset, false);

        return redirect()->away($this->cloudFront->signedFileUrl($source->key, expiresAt: $expiry))->header('Cache-Control', 'private, no-store');
    }

    public function requestDownload(Request $request, ConcertFamilyShare $share, MediaAsset $asset, string $requestUuid): ConcertFamilyDownload
    {
        $video = $this->access->video($request, $share, $asset);
        abort_unless($share->concert->studio->family_downloads_enabled, 403, 'Downloads are not enabled for this studio’s family invitations.');
        abort_unless(in_array($asset->storage_disk, ['s3_concerts', 's3_concerts_legacy'], true) || $this->stored->isLocal($asset->storage_disk), 503, 'Video delivery is temporarily unavailable. No download request was counted.');
        abort_unless($asset->storage_disk !== 's3_concerts' || $this->stored->isLocal($asset->storage_disk) || $this->cloudFront->isConfigured(), 503, 'Video delivery is temporarily unavailable. No download request was counted.');
        abort_unless(Storage::disk($asset->storage_disk)->exists($asset->storage_key), 404, 'This file is temporarily unavailable. No download request was counted.');

        return DB::transaction(function () use ($video, $share, $requestUuid) {
            $video = ConcertFamilyVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
            $previous = ConcertFamilyDownload::where('concert_family_video_id', $video->id)->where('request_uuid', $requestUuid)->first();
            if ($previous) {
                if (! $previous->expires_at->isFuture()) {
                    throw ValidationException::withMessages(['download' => 'This download request expired. Reload the page to request another attempt.']);
                }

                return $previous;
            }
            if ($video->download_requests >= 3) {
                throw ValidationException::withMessages(['download' => 'All three download requests for this video have been used. Contact DancePro if you still need help.']);
            }
            $video->increment('download_requests');
            $share->update(['last_activity_at' => now()]);

            return ConcertFamilyDownload::create(['concert_family_video_id' => $video->id, 'request_uuid' => $requestUuid,
                'expires_at' => $this->access->end($share)->min(now()->addMinutes(5))]);
        });
    }

    public function download(Request $request, ConcertFamilyShare $share, ConcertFamilyDownload $download)
    {
        abort_unless($download->video->concert_family_share_id === $share->id, 404);
        $asset = $download->video->asset;
        $this->access->video($request, $share, $asset);
        abort_unless($share->concert->studio->family_downloads_enabled && $download->expires_at->isFuture(), 403);
        $name = $this->filenames->for($share->concert, $asset);
        $expiry = $this->access->end($share)->min($download->expires_at);
        if ($asset->storage_disk === 's3_concerts_legacy' || $this->stored->isLocal($asset->storage_disk)) {
            return $this->stored->response($asset, $name, $expiry);
        }

        return redirect()->away($this->cloudFront->signedFileUrl($asset->storage_key, $name, expiresAt: $expiry))->header('Cache-Control', 'private, no-store');
    }

    public function activity(Request $request, ConcertFamilyShare $share, MediaAsset $asset, array $input): void
    {
        $video = $this->access->video($request, $share, $asset, true);
        $this->lease($share, $input['player'], ($input['paused'] ?? false) || ($input['ended'] ?? false));
        $video->update(['last_position_seconds' => $input['position'], 'last_played_at' => now(),
            'playback_completed_at' => ($input['ended'] ?? false) ? ($video->playback_completed_at ?? now()) : $video->playback_completed_at]);
        $share->update(['last_activity_at' => now()]);
    }

    private function lease(ConcertFamilyShare $share, string $player, bool $release = false): void
    {
        DB::transaction(function () use ($share, $player, $release) {
            $share = ConcertFamilyShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $player);
            abort_if($share->player_expires_at?->isFuture() && $share->player_hash !== $hash, 403,
                'This invitation is playing in another window. Pause that video or wait two minutes before trying again.');
            $share->forceFill(['player_hash' => $hash, 'player_expires_at' => $release ? now() : now()->addSeconds(90)])->save();
        });
    }
}
