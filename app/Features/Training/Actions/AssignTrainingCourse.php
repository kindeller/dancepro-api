<?php

namespace App\Features\Training\Actions;

use App\Features\Training\Models\TrainingCourse;
use Illuminate\Support\Facades\DB;

class AssignTrainingCourse
{
    public function execute(TrainingCourse $course, array $crewProfileIds, ?string $dueAt, int $assignedByUserId): void
    {
        DB::transaction(function () use ($course, $crewProfileIds, $dueAt, $assignedByUserId): void {
            $selected = collect($crewProfileIds)->map(fn ($id) => (int) $id)->unique();

            $course->enrolments()
                ->whereNotIn('crew_profile_id', $selected)
                ->whereNull('started_at')
                ->whereNull('completed_at')
                ->delete();

            foreach ($selected as $crewProfileId) {
                $enrolment = $course->enrolments()->firstOrNew(['crew_profile_id' => $crewProfileId]);
                if (! $enrolment->exists) {
                    $enrolment->status = 'assigned';
                    $enrolment->assigned_at = now();
                    $enrolment->assigned_by_user_id = $assignedByUserId;
                }
                if ($enrolment->status !== 'completed') {
                    $enrolment->due_at = $dueAt;
                }
                $enrolment->save();
            }
        });
    }
}
