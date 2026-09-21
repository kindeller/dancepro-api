<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Actions\ResolveConcertPlaybackSource;
use App\Features\Concerts\Actions\UnlockConcert;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Requests\UnlockConcertRequest;
use App\Features\Concerts\Services\ConcertAccessSession;
use App\Features\Concerts\Services\ConcertCloudFrontSigner;
use App\Features\Concerts\Services\ConcertDownloadFilename;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaType;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicConcertController extends Controller
{
    public function show(Request $request, Concert $concert, ConcertAccessSession $access): View
    {
        abort_unless($concert->isPubliclyAvailable(), 404);

        if (! $access->allows($request, $concert)) {
            return view('public.concerts.unlock', compact('concert'));
        }

        $concert->load(['studio', 'mediaCollections' => fn ($query) => $query
            ->where('status', MediaCollectionStatus::Published)
            ->orderBy('sort_order'), 'mediaCollections.assets' => fn ($query) => $query
            ->where('status', MediaAssetStatus::Available)
            ->where('is_visible', true)
            ->orderBy('sort_order')]);

        return view('public.concerts.show', [
            'concert' => $concert,
            'downloadUrls' => $concert->mediaCollections->flatMap->assets->mapWithKeys(fn (MediaAsset $asset) => [
                $asset->uuid => URL::temporarySignedRoute('concerts.media.download', now()->addMinutes(15), [
                    'concert' => $concert,
                    'asset' => $asset,
                    'access' => 1,
                ]),
            ]),
        ]);
    }

    public function unlock(UnlockConcertRequest $request, Concert $concert, UnlockConcert $unlock): RedirectResponse
    {
        abort_unless($concert->isPubliclyAvailable(), 404);

        if (! $unlock->execute($concert, $request, $request->string('student_name')->toString(), $request->string('password')->toString())) {
            return back()->withErrors(['password' => 'The concert password was not recognised.'])->onlyInput('student_name');
        }

        return redirect()->route('concerts.show', $concert);
    }

    public function playback(
        Request $request,
        Concert $concert,
        MediaAsset $asset,
        ConcertAccessSession $access,
        ResolveConcertPlaybackSource $resolvePlayback,
        ConcertCloudFrontSigner $cloudFront,
    ): JsonResponse {
        $this->authorizeAsset($request, $concert, $asset, $access);
        abort_unless($asset->media_type === MediaType::Video, 404);

        $local = app()->environment('local');
        $source = $resolvePlayback->execute($asset, ! $local && $cloudFront->canSignCookies());
        if ($local) {
            abort_unless(in_array($source->disk, ['s3_concerts', 'local'], true), 503);

            return ApiResponse::success('Concert playback source returned.', [
                'format' => $source->format->value,
                'url' => $this->temporaryPlaybackUrl($concert, $asset),
                'fallback_url' => null,
            ]);
        }
        abort_unless($cloudFront->isConfigured() && $source->disk === 's3_concerts', 503);

        if (! $source->isHls()) {
            return ApiResponse::success('Concert playback source returned.', [
                'format' => $source->format->value,
                'url' => $cloudFront->signedVideoUrl($source),
                'fallback_url' => null,
            ]);
        }

        $response = ApiResponse::success('Concert playback source returned.', [
            'format' => $source->format->value,
            'url' => $cloudFront->urlFor($source->key),
            'fallback_url' => $this->temporaryPlaybackUrl($concert, $asset, true),
        ]);

        foreach ($cloudFront->cookiesFor($source) as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    public function media(
        Request $request,
        Concert $concert,
        MediaAsset $asset,
        ConcertAccessSession $access,
        ResolveConcertPlaybackSource $resolvePlayback,
        ConcertCloudFrontSigner $cloudFront,
    ): RedirectResponse|BinaryFileResponse {
        $this->authorizeAsset($request, $concert, $asset, $access);
        abort_unless($asset->media_type === MediaType::Video, 404);

        $local = app()->environment('local');
        $source = $resolvePlayback->execute(
            $asset,
            ! $local && $cloudFront->canSignCookies() && ! $request->boolean('fallback'),
        );
        if ($local) {
            abort_unless(in_array($source->disk, ['s3_concerts', 'local'], true), 503);

            return response()->file(Storage::disk($source->disk)->path($source->key), [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'video/mp4',
            ]);
        }
        abort_unless($cloudFront->isConfigured() && $source->disk === 's3_concerts', 503);

        if ($source->isHls()) {
            $response = redirect()->away($cloudFront->urlFor($source->key));

            foreach ($cloudFront->cookiesFor($source) as $cookie) {
                $response->headers->setCookie($cookie);
            }

            return $response;
        }

        return redirect()->away($cloudFront->signedVideoUrl($source));
    }

    public function download(Request $request, Concert $concert, MediaAsset $asset, ConcertAccessSession $access, ConcertDownloadFilename $filenames, ConcertCloudFrontSigner $cloudFront): RedirectResponse|BinaryFileResponse
    {
        $this->authorizeAsset($request, $concert, $asset, $access);
        if (app()->environment('local')) {
            abort_unless(in_array($asset->storage_disk, ['s3_concerts', 'local'], true), 503);
            abort_unless(Storage::disk($asset->storage_disk)->exists($asset->storage_key), 404);

            return response()->download(Storage::disk($asset->storage_disk)->path($asset->storage_key), $filenames->for($concert, $asset), ['Cache-Control' => 'private, no-store']);
        }
        abort_unless($cloudFront->isConfigured() && $asset->storage_disk === 's3_concerts', 503);

        return redirect()->away($cloudFront->signedFileUrl($asset->storage_key, $filenames->for($concert, $asset)));
    }

    private function authorizeAsset(Request $request, Concert $concert, MediaAsset $asset, ConcertAccessSession $access): void
    {
        abort_unless($concert->isPubliclyAvailable(), 404);
        abort_unless($asset->collection()->where('concert_id', $concert->id)->where('status', MediaCollectionStatus::Published)->exists(), 404);
        abort_unless($asset->status === MediaAssetStatus::Available && $asset->is_visible, 404);
        abort_unless($access->allows($request, $concert), 403);
    }

    private function temporaryPlaybackUrl(Concert $concert, MediaAsset $asset, bool $fallback = false): string
    {
        return URL::temporarySignedRoute(
            'concerts.media.stream',
            now()->addMinutes((int) config('concerts.playback.signed_url_ttl_minutes', 15)),
            array_filter([
                'concert' => $concert,
                'asset' => $asset,
                'access' => 1,
                'fallback' => $fallback ? 1 : null,
            ]),
        );
    }
}
