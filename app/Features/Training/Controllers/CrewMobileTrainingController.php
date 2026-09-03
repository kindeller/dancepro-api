<?php

namespace App\Features\Training\Controllers;

use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Services\AvailableTrainingCourses;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileTrainingController extends Controller
{
    public function __invoke(Request $request, AvailableTrainingCourses $available): JsonResponse
    {
        $courses = $available->for($request->user()->crewProfile)->map(function (TrainingCourse $course): array {
            $enrolment = $course->enrolments->first();
            $moduleCount = $course->modules->count();
            $completed = $enrolment?->moduleProgress()->whereNotNull('completed_at')->count() ?? 0;

            return [
                'id' => $course->uuid,
                'title' => $course->title,
                'status' => $enrolment?->status ?? 'available',
                'due_at' => $enrolment?->due_at?->startOfDay()->toIso8601String(),
                'progress' => $moduleCount > 0 ? (int) round(($completed / $moduleCount) * 100) : 0,
            ];
        });

        return ApiResponse::success('Training returned.', $courses);
    }
}
