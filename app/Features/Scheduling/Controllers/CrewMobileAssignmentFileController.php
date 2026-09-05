<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\CrewMobileAssignmentFiles;
use App\Features\Scheduling\Services\CrewMobileAssignments;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewMobileAssignmentFileController extends Controller
{
    public function __invoke(
        Request $request,
        SchedulingShiftAssignment $assignment,
        string $kind,
        CrewMobileAssignments $assignments,
        CrewMobileAssignmentFiles $files,
    ): StreamedResponse {
        $assignment = $assignments->findFor($request->user()->crewProfile, $assignment);

        return $files->response($assignment, $kind);
    }

    public function download(Request $request, SchedulingShiftAssignment $assignment, string $kind, CrewMobileAssignments $assignments, CrewMobileAssignmentFiles $files): JsonResponse
    {
        $assignment = $assignments->findFor($request->user()->crewProfile, $assignment);
        $download = $files->issue($assignment, $kind);
        Log::info('Mobile assignment file download issued.', [
            'user_id' => $request->user()->id,
            'assignment_uuid' => $assignment->uuid,
            'file_kind' => $kind,
            'expires_at' => $download['expires_at'],
        ]);

        return ApiResponse::success('Temporary assignment file download issued.', $download)
            ->header('Cache-Control', 'private, no-store');
    }

    public function content(SchedulingShiftAssignment $assignment, string $kind, CrewMobileAssignmentFiles $files): StreamedResponse
    {
        abort_unless($assignment->status === 'published', 404);

        return $files->response($assignment, $kind);
    }
}
