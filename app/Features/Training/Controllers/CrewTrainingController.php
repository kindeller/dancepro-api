<?php

namespace App\Features\Training\Controllers;

use App\Features\Training\Actions\CompleteTrainingModule;
use App\Features\Training\Models\TrainingAssessmentAttempt;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingModule;
use App\Features\Training\Requests\CompleteTrainingModuleRequest;
use App\Features\Training\Services\AvailableTrainingCourses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CrewTrainingController extends Controller
{
    public function index(AvailableTrainingCourses $available): View
    {
        $profile = request()->user()->crewProfile;

        return view('crew.training.index', ['courses' => $available->for($profile)]);
    }

    public function show(TrainingCourse $trainingCourse, AvailableTrainingCourses $available): View
    {
        $profile = request()->user()->crewProfile;
        abort_unless($available->for($profile)->contains('id', $trainingCourse->id), 403);
        $trainingCourse->load(['sections.modules', 'modules']);
        $enrolment = TrainingEnrolment::query()->firstOrCreate(
            ['training_course_id' => $trainingCourse->id, 'crew_profile_id' => $profile->id],
            ['status' => 'in_progress', 'started_at' => now()],
        );
        if ($enrolment->status === 'assigned') {
            $enrolment->update(['status' => 'in_progress', 'started_at' => now()]);
        }
        $enrolment->load('moduleProgress');
        $assessmentAttempts = TrainingAssessmentAttempt::query()->where('training_enrolment_id', $enrolment->id)->orderByDesc('attempt_number')->get()->groupBy('training_module_id');

        return view('crew.training.show', compact('trainingCourse', 'enrolment', 'assessmentAttempts'));
    }

    public function complete(CompleteTrainingModuleRequest $request, TrainingCourse $trainingCourse, TrainingModule $module, AvailableTrainingCourses $available, CompleteTrainingModule $complete): RedirectResponse
    {
        $profile = $request->user()->crewProfile;
        abort_unless($module->training_course_id === $trainingCourse->id && $available->for($profile)->contains('id', $trainingCourse->id), 403);
        $enrolment = TrainingEnrolment::query()->firstOrCreate(
            ['training_course_id' => $trainingCourse->id, 'crew_profile_id' => $profile->id],
            ['status' => 'in_progress', 'started_at' => now()],
        );
        if ($enrolment->status === 'assigned') {
            $enrolment->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        return back()->with('status', $complete->execute($enrolment, $module, $request->validated()));
    }
}
