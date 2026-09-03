<?php

namespace App\Features\Training\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;

class StartTrainingCourse
{
    public function execute(CrewProfile $profile, TrainingCourse $course): TrainingEnrolment
    {
        $enrolment = TrainingEnrolment::query()->firstOrCreate(
            ['training_course_id' => $course->id, 'crew_profile_id' => $profile->id],
            ['status' => 'in_progress', 'started_at' => now()],
        );

        if ($enrolment->status === 'assigned') {
            $enrolment->update([
                'status' => 'in_progress',
                'started_at' => $enrolment->started_at ?? now(),
            ]);
        }

        return $enrolment->load('moduleProgress');
    }
}
