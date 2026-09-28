<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\PublishConcertTitleCards;
use App\Features\Programmes\Models\ProgrammeRevision;
use App\Features\Programmes\Requests\PublishConcertTitleCardsRequest;
use App\Features\Programmes\Services\ConcertTitlePackages;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConcertTitlePackageController extends Controller
{
    public function publish(PublishConcertTitleCardsRequest $request, SchedulingEvent $schedulingEvent, PublishConcertTitleCards $publish): JsonResponse
    {
        Gate::authorize('updateProgramme', $schedulingEvent);
        $package = $publish->execute($schedulingEvent, $request->user(), $request->validated(), $request->file('package'));

        return ApiResponse::success('Video titles ready for the uploader.', ['uuid' => $package->uuid]);
    }

    public function show(SchedulingEvent $schedulingEvent, ConcertTitlePackages $packages): JsonResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);
        $package = $packages->downloadable($schedulingEvent);

        return ApiResponse::success('', $package ? [
            'ready' => true, 'event_uuid' => $schedulingEvent->uuid, 'package_uuid' => $package->uuid,
            'revision_uuid' => ProgrammeRevision::findOrFail($package->programme_revision_id)->uuid,
            'item_count' => $package->item_count, 'size_bytes' => $package->size_bytes, 'sha256' => $package->sha256,
            'download_url' => route('staff.programmes.title-cards.download', [$schedulingEvent, 'package' => $package->uuid], false),
        ] : ['ready' => false, 'event_uuid' => $schedulingEvent->uuid])->header('Cache-Control', 'private, no-store');
    }

    public function download(SchedulingEvent $schedulingEvent, string $package, ConcertTitlePackages $packages): StreamedResponse
    {
        Gate::authorize('viewProgramme', $schedulingEvent);
        $current = $packages->downloadable($schedulingEvent);
        abort_unless($current && hash_equals($current->uuid, $package), 404);
        $disk = Storage::disk($current->disk);
        abort_unless($disk->exists($current->path), 404);

        return $disk->response($current->path, 'Video titles '.$current->uuid.'.zip', ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'attachment');
    }
}
