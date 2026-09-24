<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Requests\AdminConcertPhotoGalleryRequest;
use App\Features\Media\Requests\UpdateConcertPhotoAlbumRequest;
use App\Features\Media\Requests\UpdateConcertPhotoCoverRequest;
use App\Features\Media\Services\ConcertPhotoGallerySetup;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Services\ProgrammePhotoPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConcertPhotoGalleryController extends Controller
{
    public function events(AdminConcertPhotoGalleryRequest $request, ConcertPhotoGallerySetup $gallery): Response
    {
        return response()->view('admin.media.concert-photo-events', ['events' => $gallery->events()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(AdminConcertPhotoGalleryRequest $request, SchedulingEvent $schedulingEvent, ConcertPhotoGallerySetup $gallery): Response
    {
        return response()->view('admin.media.concert-photo-gallery', $gallery->editor($schedulingEvent))
            ->header('Cache-Control', 'private, no-store');
    }

    public function sync(AdminConcertPhotoGalleryRequest $request, SchedulingEvent $schedulingEvent, ConcertPhotoGallerySetup $gallery): RedirectResponse
    {
        $count = $gallery->sync($schedulingEvent);

        return back()->with('status', $count.' verified photo'.($count === 1 ? '' : 's').' synced into private customer albums.');
    }

    public function update(UpdateConcertPhotoAlbumRequest $request, SchedulingEvent $schedulingEvent, MediaCollection $collection, ConcertPhotoGallerySetup $gallery): RedirectResponse
    {
        $gallery->updateAlbum($schedulingEvent, $collection, $request->validated());

        return back()->with('status', 'Album updated.');
    }

    public function updateCover(UpdateConcertPhotoCoverRequest $request, SchedulingEvent $schedulingEvent, MediaCollection $collection, ConcertPhotoReleases $releases): RedirectResponse
    {
        abort_unless($schedulingEvent->concertBookingItem?->concert?->id === $collection->concert_id, 404);
        $releases->updateAlbumCover($collection, $request->user(), $request->validated('asset_uuid'));

        return back()->with('status', $request->validated('asset_uuid') ? 'Album cover updated.' : 'Album cover returned to the first photo.');
    }

    public function image(AdminConcertPhotoGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, ProgrammePhotoPreview $preview): StreamedResponse
    {
        abort_unless($schedulingEvent->event_type->value === 'concert', 404);
        $gallery = ProgrammePhotoGallery::where('scheduling_event_id', $schedulingEvent->id)->firstOrFail();
        $file = ProgrammePhotoFile::inGallery($gallery)->where('programme_photo_files.uuid', $photo)->firstOrFail();
        abort_unless($file->storage_disk === 'local' && Storage::disk('local')->exists($file->storage_key), 404);

        return Storage::disk('local')->response($preview->key($file, $request->validated('size', 'preview'), $schedulingEvent),
            $file->uuid.'.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store'], 'inline');
    }
}
