<?php

namespace App\Features\Competition\Controllers;

use App\Features\Competition\Actions\ListCompetitionObjects;
use App\Features\Competition\Requests\ListCompetitionObjectsRequest;
use App\Features\Programmes\Requests\AdminCompetitionVideoRequest;
use App\Features\Programmes\Services\CompetitionMediaVideos;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCompetitionObjectController extends Controller
{
    private const DEFAULT_CHUNK_SIZE = 250;

    public function index(
        ListCompetitionObjectsRequest $request,
        ListCompetitionObjects $listCompetitionObjects,
        CompetitionMediaVideos $videos,
    ): View {
        Gate::authorize('viewCompetitionObjects');

        $objects = $listCompetitionObjects->handle(
            $request->string('prefix')->toString() ?: null,
            $request->integer('limit') ?: self::DEFAULT_CHUNK_SIZE,
            $request->string('continuation_token')->toString() ?: null,
        );

        return view('admin.competition.objects.index', [
            'objects' => $objects,
            'prefix' => $objects['prefix'],
            'limit' => $objects['pagination']['limit'],
            'videoEvent' => $videos->eventForPrefix($objects['prefix']),
        ]);
    }

    public function syncVideos(
        AdminCompetitionVideoRequest $request,
        SchedulingEvent $schedulingEvent,
        CompetitionMediaVideos $videos,
    ): RedirectResponse {
        Gate::authorize('viewCompetitionObjects');
        $result = $videos->sync($schedulingEvent);
        $message = $result['linked'].' existing '.str('video')->plural($result['linked']).' linked to the customer gallery.';
        if ($result['existing']) {
            $message .= ' '.$result['existing'].' already linked.';
        }
        if ($result['unmatched']) {
            $message .= ' '.count($result['unmatched']).' filename(s) could not be matched and were left unchanged.';
        }

        return to_route('admin.competition.objects.index', ['prefix' => $result['prefix']])->with('status', $message);
    }

    public function chunk(
        ListCompetitionObjectsRequest $request,
        ListCompetitionObjects $listCompetitionObjects,
    ): JsonResponse {
        Gate::authorize('viewCompetitionObjects');

        $objects = $listCompetitionObjects->handle(
            $request->string('prefix')->toString() ?: null,
            $request->integer('limit') ?: self::DEFAULT_CHUNK_SIZE,
            $request->string('continuation_token')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'data' => $objects,
        ]);
    }
}
