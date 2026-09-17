<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Actions\DeleteManagedConcertMedia;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Requests\ConfirmMediaDeletionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminConcertMediaDeletionController extends Controller
{
    public function confirmAsset(MediaAsset $asset, DeleteManagedConcertMedia $deletion): View
    {
        Gate::authorize('update', $asset);
        $keys = $deletion->keysForAsset($asset);

        return view('admin.concerts.confirm-media-deletion', [
            'concert' => $asset->collection->concert,
            'label' => $asset->display_name,
            'disk' => $asset->storage_disk,
            'keys' => $keys,
            'digest' => $deletion->digest($asset->storage_disk.':'.$asset->uuid, $keys),
            'action' => route('admin.concerts.media.assets.destroy', $asset),
        ]);
    }

    public function destroyAsset(ConfirmMediaDeletionRequest $request, MediaAsset $asset, DeleteManagedConcertMedia $deletion): RedirectResponse
    {
        Gate::authorize('update', $asset);
        $deletion->deleteAsset($asset, $request->validated('digest'));

        return redirect()->route('admin.concerts.edit', $asset->collection->concert)->with('status', 'Video and its storage objects deleted.');
    }

    public function confirmCollection(MediaCollection $collection, DeleteManagedConcertMedia $deletion): View
    {
        Gate::authorize('update', $collection);
        $keys = $deletion->keysForCollection($collection);

        return view('admin.concerts.confirm-media-deletion', [
            'concert' => $collection->concert,
            'label' => $collection->name,
            'disk' => $collection->storage_disk,
            'keys' => $keys,
            'digest' => $deletion->digest($collection->storage_disk.':'.$collection->uuid, $keys),
            'action' => route('admin.concerts.media.collections.destroy', $collection),
        ]);
    }

    public function destroyCollection(ConfirmMediaDeletionRequest $request, MediaCollection $collection, DeleteManagedConcertMedia $deletion): RedirectResponse
    {
        Gate::authorize('update', $collection);
        $deletion->deleteCollection($collection, $request->validated('digest'));

        return redirect()->route('admin.concerts.edit', $collection->concert)->with('status', 'Collection, videos and storage objects deleted.');
    }
}
