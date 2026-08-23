<?php

namespace App\Features\Concerts\Controllers;

use App\Features\Concerts\Actions\UnlockConcert;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Requests\UnlockConcertRequest;
use App\Features\Concerts\Services\ConcertAccessSession;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function media(Request $request, Concert $concert, MediaAsset $asset, ConcertAccessSession $access): StreamedResponse
    {
        $this->authorizeAsset($request, $concert, $asset, $access);

        return Storage::disk($asset->storage_disk)->response(
            $asset->storage_key,
            $asset->display_name ?? $asset->original_filename,
            ['Content-Type' => $asset->mime_type ?? 'application/octet-stream'],
        );
    }

    public function download(Request $request, Concert $concert, MediaAsset $asset, ConcertAccessSession $access): StreamedResponse
    {
        $this->authorizeAsset($request, $concert, $asset, $access);

        return Storage::disk($asset->storage_disk)->download(
            $asset->storage_key,
            $asset->original_filename ?? basename($asset->storage_key),
        );
    }

    private function authorizeAsset(Request $request, Concert $concert, MediaAsset $asset, ConcertAccessSession $access): void
    {
        abort_unless($concert->isPubliclyAvailable(), 404);
        abort_unless($asset->collection()->where('concert_id', $concert->id)->exists(), 404);
        abort_unless($asset->status === MediaAssetStatus::Available && $asset->is_visible, 404);
        abort_unless($access->allows($request, $concert), 403);
    }
}
