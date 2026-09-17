<?php

namespace App\Features\Media\Controllers;

use App\Features\Admin\Actions\ManageBrandingMedia;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Services\ConcertAccessSession;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBrandingMediaController extends Controller
{
    public function studioCover(Studio $studio, ManageBrandingMedia $media): StreamedResponse
    {
        return $this->serve($studio->cover_image_storage_key, $studio->cover_image_mime_type, $media->key($studio, 'cover'), $media);
    }

    public function concertCover(Concert $concert, ManageBrandingMedia $media): StreamedResponse
    {
        if (! $concert->isPubliclyAvailable()) {
            Gate::authorize('manageConcerts');
        }

        return $this->serve($concert->cover_image_storage_key, $concert->cover_image_mime_type, $media->key($concert, 'cover'), $media);
    }

    public function program(Request $request, Concert $concert, ConcertAccessSession $access, ManageBrandingMedia $media): StreamedResponse
    {
        if (! $concert->isPubliclyAvailable()) {
            Gate::authorize('manageConcerts');
        } elseif (! Gate::allows('manageConcerts')) {
            abort_unless($access->allows($request, $concert), 403);
        }

        return $this->serve($concert->program_storage_key, $concert->program_mime_type, $media->key($concert, 'program'), $media);
    }

    private function serve(?string $storedKey, ?string $mimeType, string $expectedKey, ManageBrandingMedia $media): StreamedResponse
    {
        abort_unless($storedKey === $expectedKey, 404);
        $disk = Storage::disk($media->disk());
        abort_unless($disk->exists($expectedKey), 404);
        return $disk->response($expectedKey, null, ['Content-Type' => $mimeType ?? 'application/octet-stream', 'Cache-Control' => 'private, no-store']);
    }
}
