<?php

namespace App\Features\Media\Controllers;

use App\Features\Admin\Actions\ManageBrandingMedia;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Services\ConcertAccessSession;
use App\Features\Concerts\Services\ConcertCloudFrontSigner;
use App\Features\Media\Services\StoredGalleryCover;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBrandingMediaController extends Controller
{
    public function studioCover(Studio $studio, ManageBrandingMedia $media, StoredGalleryCover $covers): StreamedResponse
    {
        abort_unless($studio->cover_image_storage_key === $media->key($studio, 'cover'), 404);

        return $covers->response($media->disk(), $studio->cover_image_storage_key, $studio->cover_image_revision);
    }

    public function concertCover(Concert $concert, ManageBrandingMedia $media, StoredGalleryCover $covers): StreamedResponse
    {
        if (! $concert->isPubliclyAvailable()) {
            Gate::authorize('manageConcerts');
        }

        abort_unless($concert->cover_image_storage_key === $media->key($concert, 'cover'), 404);

        return $covers->response($media->disk(), $concert->cover_image_storage_key, $concert->cover_image_revision);
    }

    public function program(Request $request, Concert $concert, ConcertAccessSession $access, ManageBrandingMedia $media, ConcertCloudFrontSigner $cloudFront): RedirectResponse|StreamedResponse
    {
        if (! $concert->isPubliclyAvailable()) {
            Gate::authorize('manageConcerts');
        } elseif (! Gate::allows('manageConcerts')) {
            abort_unless($access->allows($request, $concert), 403);
        }

        return $this->serve($concert->program_storage_key, $concert->program_revision, $concert->program_mime_type, $media->key($concert, 'program'), $media, $cloudFront);
    }

    private function serve(?string $storedKey, ?string $revision, ?string $mimeType, string $expectedKey, ManageBrandingMedia $media, ConcertCloudFrontSigner $cloudFront): RedirectResponse|StreamedResponse
    {
        abort_unless($storedKey === $expectedKey, 404);
        if (app()->environment('local')) {
            abort_unless($media->disk() === 's3_concerts', 503);
            abort_unless(Storage::disk($media->disk())->exists($expectedKey), 404);

            return Storage::disk($media->disk())->response($expectedKey, null, [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => $mimeType ?: (str_ends_with($expectedKey, '.pdf') ? 'application/pdf' : 'image/jpeg'),
            ]);
        }
        abort_unless($media->disk() === 's3_concerts' && $cloudFront->isConfigured(), 503);

        return redirect()->away($cloudFront->signedFileUrl($expectedKey, revision: $revision))
            ->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
