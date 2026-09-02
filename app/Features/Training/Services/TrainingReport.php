<?php

namespace App\Features\Training\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use Illuminate\Support\Collection;

class TrainingReport
{
    public function overview(?string $status = null, ?int $courseId = null, ?string $search = null): array
    {
        $courses = TrainingCourse::query()->where('status', 'published')->with('modules')->orderBy('title')->get();
        $profiles = CrewProfile::query()->with(['user', 'trainingEnrolments' => fn ($query) => $query
            ->with(['course', 'moduleProgress', 'reminders' => fn ($reminders) => $reminders->latest('reminded_at')])
            ->whereIn('training_course_id', $courses->pluck('id'))])->whereHas('user')->get()
            ->sortBy(fn (CrewProfile $profile) => strtolower($this->crewName($profile)))->values();

        if ($search) {
            $needle = strtolower($search);
            $profiles = $profiles->filter(fn (CrewProfile $profile) => str_contains(strtolower($this->crewName($profile).' '.$profile->user->email), $needle))->values();
        }
        if ($courseId) {
            $courses = $courses->where('id', $courseId)->values();
        }
        if ($status) {
            $profiles = $profiles->filter(fn (CrewProfile $profile) => $courses->contains(fn (TrainingCourse $course) => $this->status($profile->trainingEnrolments->firstWhere('training_course_id', $course->id)) === $status))->values();
        }

        return compact('courses', 'profiles');
    }

    public function crewHistory(CrewProfile $profile): CrewProfile
    {
        return $profile->load(['user', 'trainingEnrolments' => fn ($query) => $query->with(['course.modules', 'moduleProgress', 'assessmentAttempts', 'reminders.recordedBy'])->latest()]);
    }

    public function exportRows(): Collection
    {
        $data = $this->overview();

        return $data['profiles']->flatMap(function (CrewProfile $profile) use ($data): array {
            return $data['courses']->map(function (TrainingCourse $course) use ($profile): array {
                $enrolment = $profile->trainingEnrolments->firstWhere('training_course_id', $course->id);

                return [
                    $this->crewName($profile),
                    $profile->user->email,
                    $course->title,
                    $course->is_required ? 'Required' : 'Optional',
                    ucfirst(str_replace('_', ' ', $this->status($enrolment))),
                    $enrolment?->due_at?->format('Y-m-d'),
                    $enrolment?->started_at?->format('Y-m-d H:i'),
                    $enrolment?->completed_at?->format('Y-m-d H:i'),
                    $enrolment?->moduleProgress->whereNotNull('completed_at')->count() ?? 0,
                    $course->modules->count(),
                ];
            })->all();
        });
    }

    public function status(?TrainingEnrolment $enrolment): string
    {
        if (! $enrolment) {
            return 'not_assigned';
        }
        if ($enrolment->status === 'completed') {
            return 'completed';
        }
        if ($enrolment->due_at?->isPast()) {
            return 'overdue';
        }

        return $enrolment->status;
    }

    public function crewName(CrewProfile $profile): string
    {
        return $profile->preferred_name ?: $profile->legal_name ?: $profile->user->name;
    }
}
