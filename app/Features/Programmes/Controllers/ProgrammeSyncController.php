<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\SyncProgramme;
use App\Features\Programmes\Requests\SyncProgrammeRequest;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgrammeSyncController extends Controller
{
    public function __invoke(SyncProgrammeRequest $request, SchedulingEvent $schedulingEvent, SyncProgramme $sync): JsonResponse
    {
        $plan = $sync->execute($schedulingEvent, $request->user(), $request->validated('revision_uuid'), $request->validated('nodes'), $request->validated('base_plan_uuid'));

        return ApiResponse::success('Working programme synced. Photos have not been uploaded.', [
            'uuid' => $plan->uuid, 'event_uuid' => $schedulingEvent->uuid,
            'revision_uuid' => $plan->snapshot['revision_uuid'], 'digest' => $plan->digest,
        ])->header('Cache-Control', 'private, no-store');
    }
}
