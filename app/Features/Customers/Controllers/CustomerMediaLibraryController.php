<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerMediaReviewExamples;
use App\Features\Customers\Services\CustomerPurchasedMedia;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerMediaLibraryController extends Controller
{
    public function show(CustomerCompetitionsRequest $request, string $kind, string $event, CustomerPurchasedMedia $purchases, CustomerMediaReviewExamples $examples): Response
    {
        $owned = $purchases->forUser($request->user());
        $card = collect([...$owned['photos'], ...$owned['videos']])
            ->first(fn (array $entry) => $entry['kind'] === $kind && $entry['event']->uuid === $event);
        if ($card) {
            abort_unless($card['can_browse'], 403);
            $album = $purchases->albumForUser($request->user(), $kind, $event);
        } else {
            $review = $examples->forUser($request->user());
            $album = collect([...$review['photos'], ...$review['videos']])
                ->first(fn (array $entry) => $entry['kind'] === $kind && $entry['event']->uuid === $event);
            abort_unless($album, 404);
        }

        return response()->view('customers.media.library', $album)->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function thumbnail(CustomerCompetitionsRequest $request, string $kind, string $event, string $asset, CustomerPurchasedMedia $purchases, CustomerCompetitionAccess $access): StreamedResponse
    {
        $album = $purchases->albumForUser($request->user(), $kind, $event);
        if ($kind === 'competition') {
            abort_unless($access->allows($request->user(), $album['event']), 403);
        }
        $media = $album['assets']->firstWhere('uuid', $asset);
        abort_unless($media && $media->thumbnail_storage_disk && $media->thumbnail_storage_key, 404);
        $disk = Storage::disk($media->thumbnail_storage_disk);
        abort_unless($disk->exists($media->thumbnail_storage_key), 404);

        return $disk->response($media->thumbnail_storage_key, null, ['Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff'], 'inline');
    }
}
