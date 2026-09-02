<?php

namespace App\Features\Training\Actions;

use App\Features\Crew\Models\CrewRoleQualification;
use App\Features\Crew\Support\CrewRoleQualificationStatus;
use App\Features\Training\Models\TrainingEnrolment;

class AwardTrainingRoleQualification
{
    public function execute(TrainingEnrolment $enrolment): ?CrewRoleQualification
    {
        $course = $enrolment->course;
        if (! $course->grants_role_qualification || ! $course->crew_role_id) {
            return null;
        }

        $qualification = CrewRoleQualification::query()->firstOrNew([
            'crew_profile_id' => $enrolment->crew_profile_id,
            'crew_role_id' => $course->crew_role_id,
        ]);
        $qualification->status = CrewRoleQualificationStatus::Approved;
        $qualification->effective_from ??= today();
        $qualification->effective_until = null;
        $qualification->save();

        return $qualification;
    }
}
