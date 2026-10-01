<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Services\CustomerCompetitionAccess;
use App\Features\Customers\Services\CustomerMediaArchives;
use App\Features\Customers\Services\CustomerMediaReviewExamples;
use App\Features\Customers\Services\CustomerPurchasedMedia;
use App\Features\Media\Services\StoredGalleryCover;
use App\Features\Orders\Actions\RecordPurchasedMediaDownload;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
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
            $album = $purchases->albumForUser($request->user(), $kind, $event, $request->validated('routine'));
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
            abort_unless(collect($purchases->forUser($request->user())['photos'])->contains(fn ($card) => $card['event']->uuid === $event && $card['can_browse']), 403);
        }
        $media = $album['assets']->firstWhere('uuid', $asset);
        abort_unless($media, 404);
        if ($request->validated('size') === 'cover') {
            return app(StoredGalleryCover::class)->response(
                $media->storage_disk, $media->storage_key, (string) $media->updated_at);
        }
        abort_unless($media && $media->thumbnail_storage_disk && $media->thumbnail_storage_key, 404);
        $disk = Storage::disk($media->thumbnail_storage_disk);
        abort_unless($disk->exists($media->thumbnail_storage_key), 404);

        return $disk->response($media->thumbnail_storage_key, null, ['Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function downloadAll(CustomerCompetitionsRequest $request, string $kind, string $event, CustomerPurchasedMedia $purchases, CustomerMediaArchives $archives, RecordPurchasedMediaDownload $record): StreamedResponse
    {
        $album = $purchases->albumForUser($request->user(), $kind, $event, $request->validated('routine'));
        $assets = $album['assets'];
        abort_if($assets->count() < 2, 404);
        foreach ($assets as $asset) {
            if ($entitlement = $album['entitlementByAsset']->get($asset->id)) {
                app(AuthorizePurchasedMediaDownload::class)->execute($request->user(), $entitlement, $asset);
            }
        }
        $archiveTitle = $album['selectedFolder']['title'] ?? $album['title'];
        $archive = $archives->archive($request->user(), $kind, $event, $archiveTitle, $assets);
        foreach ($assets as $asset) {
            if ($entitlement = $album['entitlementByAsset']->get($asset->id)) {
                $record->execute($request->user(), $entitlement, $asset, $request);
            }
        }
        $archive->forceFill([
            'download_count' => $archive->download_count + 1,
            'last_download_at' => now(),
            'expires_at' => now()->addDays($archive->scope === 'shared' ? 30 : 2),
        ])->save();

        return Storage::disk($archive->storage_disk)->download($archive->storage_key, $archive->filename, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
