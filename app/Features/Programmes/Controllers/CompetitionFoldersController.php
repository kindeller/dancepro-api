<?php

namespace App\Features\Programmes\Controllers;

use App\Features\Programmes\Actions\ReportCompetitionFolders;
use App\Features\Programmes\Requests\CompetitionFolderInventoryRequest;
use App\Features\Programmes\Requests\CompetitionFolderProgressRequest;
use App\Features\Programmes\Services\CompetitionFolderProgress;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompetitionFoldersController extends Controller
{
    public function store(CompetitionFolderInventoryRequest $request, SchedulingEvent $schedulingEvent, ReportCompetitionFolders $report): JsonResponse
    {
        return ApiResponse::success('Competition folders checked.', $report->execute($schedulingEvent, $request->user(), $request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function show(CompetitionFolderProgressRequest $request, SchedulingEvent $schedulingEvent, CompetitionFolderProgress $progress): JsonResponse
    {
        return ApiResponse::success('Competition folder differences.', $progress->forEvent($schedulingEvent, $request->validated('revision_uuid')))->header('Cache-Control', 'private, no-store');
    }
}
