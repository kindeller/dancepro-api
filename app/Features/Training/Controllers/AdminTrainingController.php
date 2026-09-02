<?php

namespace App\Features\Training\Controllers;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Training\Actions\AssignTrainingCourse;
use App\Features\Training\Actions\LogTrainingReminder;
use App\Features\Training\Actions\SaveTrainingCourse;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Requests\AssignTrainingCourseRequest;
use App\Features\Training\Requests\LogTrainingReminderRequest;
use App\Features\Training\Requests\SaveTrainingCourseRequest;
use App\Features\Training\Requests\TrainingReportRequest;
use App\Features\Training\Services\TrainingReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTrainingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew-management.training', [
            'courses' => TrainingCourse::query()->with(['role', 'modules'])->withCount(['enrolments', 'enrolments as completions_count' => fn ($query) => $query->where('status', 'completed')])->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageCrew');

        return $this->form(new TrainingCourse);
    }

    public function store(SaveTrainingCourseRequest $request, SaveTrainingCourse $save): RedirectResponse
    {
        $course = $save->execute($request->validated());

        return redirect()->route('admin.training-courses.edit', $course)->with('status', 'Training course created.');
    }

    public function edit(TrainingCourse $trainingCourse): View
    {
        Gate::authorize('manageCrew');

        return $this->form($trainingCourse->load(['sections.modules', 'modules']));
    }

    public function assignments(TrainingCourse $trainingCourse): View
    {
        Gate::authorize('manageCrew');

        $trainingCourse->load(['role', 'modules', 'enrolments.crewProfile.user', 'enrolments.moduleProgress']);

        return view('admin.crew-management.training-assignments', [
            'course' => $trainingCourse,
            'crewProfiles' => CrewProfile::query()->with('user')->whereHas('user')->get()
                ->sortBy(fn (CrewProfile $profile) => strtolower($profile->preferred_name ?: $profile->legal_name ?: $profile->user->name)),
        ]);
    }

    public function updateAssignments(AssignTrainingCourseRequest $request, TrainingCourse $trainingCourse, AssignTrainingCourse $assign): RedirectResponse
    {
        $assign->execute($trainingCourse, $request->validated('crew_profile_ids'), $request->validated('due_at'), $request->user()->id);

        return back()->with('status', 'Training assignments updated.');
    }

    public function overview(TrainingReportRequest $request, TrainingReport $report): View
    {
        $filters = $request->validated();

        return view('admin.crew-management.training-overview', [
            ...$report->overview($filters['status'] ?? null, isset($filters['course_id']) ? (int) $filters['course_id'] : null, $filters['search'] ?? null),
            'filters' => $filters,
            'allCourses' => TrainingCourse::query()->where('status', 'published')->orderBy('title')->get(),
            'report' => $report,
        ]);
    }

    public function crewHistory(CrewProfile $crewProfile, TrainingReport $report): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew-management.training-history', ['profile' => $report->crewHistory($crewProfile), 'report' => $report]);
    }

    public function logReminder(LogTrainingReminderRequest $request, TrainingEnrolment $trainingEnrolment, LogTrainingReminder $log): RedirectResponse
    {
        $log->execute($trainingEnrolment, $request->validated(), $request->user()->id);

        return back()->with('status', 'Reminder recorded.');
    }

    public function export(TrainingReportRequest $request, TrainingReport $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Crew member', 'Email', 'Course', 'Requirement', 'Status', 'Due date', 'Started', 'Completed', 'Modules completed', 'Total modules']);
            foreach ($report->exportRows() as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, 'dancepro-training-report-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function update(SaveTrainingCourseRequest $request, TrainingCourse $trainingCourse, SaveTrainingCourse $save): RedirectResponse
    {
        if ($trainingCourse->enrolments()->where('status', 'completed')->exists()) {
            return back()->withErrors(['course' => 'Completed training records are permanent. Create a renewal course instead of changing this version.']);
        }

        $save->execute($request->validated(), $trainingCourse);

        return back()->with('status', 'Training course updated.');
    }

    private function form(TrainingCourse $course): View
    {
        return view('admin.crew-management.training-form', [
            'course' => $course,
            'roles' => CrewRole::query()->where('is_active', true)->orderBy('name')->get(),
            'courses' => TrainingCourse::query()->when($course->exists, fn ($query) => $query->where('id', '!=', $course->id))->orderBy('title')->get(),
        ]);
    }
}
