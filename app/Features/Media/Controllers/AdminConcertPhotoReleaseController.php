<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Media\Requests\ReleaseConcertPhotoGroupRequest;
use App\Features\Media\Requests\StoreConcertPhotoReleaseGroupRequest;
use App\Features\Media\Requests\UpdateConcertPhotoCoverRequest;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AdminConcertPhotoReleaseController extends Controller
{
    public function index(ReleaseConcertPhotoGroupRequest $request): RedirectResponse
    {
        return redirect()->route('admin.concert-tracker.index', ['year' => now()->year], 302, ['Cache-Control' => 'private, no-store']);
    }

    public function store(StoreConcertPhotoReleaseGroupRequest $request, ConcertPhotoReleases $releases): RedirectResponse
    {
        if ($request->filled('event_uuids')) {
            $group = $releases->releaseEvents($request->user(), $request->validated());

            return to_route('admin.concert-photo-releases.email', $group)->with('status', 'Selected events released together. Review the customer, guest and studio announcements before sending.');
        }
        $group = $releases->create($request->user(), $request->validated());

        return back()->with('status', $group->name.' release group created. Review it, then release all albums together.');
    }

    public function release(ReleaseConcertPhotoGroupRequest $request, ConcertPhotoReleaseGroup $group, ConcertPhotoReleases $releases): RedirectResponse
    {
        $releases->release($group, $request->user());

        return to_route('admin.concert-photo-releases.email', $group)->with('status', $group->name.' released. Review the customer, guest and studio announcements before sending.');
    }

    public function updateCover(UpdateConcertPhotoCoverRequest $request, ConcertPhotoReleaseGroup $group, ConcertPhotoReleases $releases): RedirectResponse
    {
        $releases->updateGroupCover($group, $request->user(), $request->validated('asset_uuid'));

        return back()->with('status', $request->validated('asset_uuid') ? $group->name.' cover updated.' : $group->name.' cover returned to automatic selection.');
    }
}
