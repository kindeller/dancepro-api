<?php

namespace App\Features\Training\Controllers;

use App\Features\Training\Actions\CompleteTrainingModule;
use App\Features\Training\Actions\StartTrainingCourse;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingModule;
use App\Features\Training\Requests\CompleteTrainingModuleRequest;
use App\Features\Training\Services\AvailableTrainingCourses;
use App\Features\Training\Services\CrewMobileTraining;
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

    public function show(Request $request, TrainingCourse $course, CrewMobileTraining $training): JsonResponse
    {
        $course = $training->course($request->user()->crewProfile, $course);
        $enrolment = $training->enrolment($request->user()->crewProfile, $course);

        return ApiResponse::success('Training course returned.', $training->detail($course, $enrolment));
    }

    public function start(Request $request, TrainingCourse $course, CrewMobileTraining $training, StartTrainingCourse $start): JsonResponse
    {
        $course = $training->course($request->user()->crewProfile, $course);
        $enrolment = $start->execute($request->user()->crewProfile, $course);

        return ApiResponse::success('Training course started.', $training->detail($course, $enrolment));
    }

    public function complete(CompleteTrainingModuleRequest $request, TrainingCourse $course, TrainingModule $module, CrewMobileTraining $training, StartTrainingCourse $start, CompleteTrainingModule $complete): JsonResponse
    {
        $course = $training->course($request->user()->crewProfile, $course);
        abort_unless($module->training_course_id === $course->id, 404);
        $enrolment = $start->execute($request->user()->crewProfile, $course);
        $message = $complete->execute($enrolment, $module, $request->validated());

        return ApiResponse::success($message, $training->detail($course->refresh(), $enrolment->refresh()->load('moduleProgress')));
    }
}
