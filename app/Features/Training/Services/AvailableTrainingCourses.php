<?php

namespace App\Features\Training\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Training\Models\TrainingCourse;
use Illuminate\Database\Eloquent\Collection;

class AvailableTrainingCourses
{
    public function for(CrewProfile $profile): Collection
    {
        $eligibleRoleIds = $profile->roleQualifications()->whereIn('status', ['approved', 'training'])->pluck('crew_role_id');

        return TrainingCourse::query()
            ->with(['role', 'sections.modules', 'modules', 'enrolments' => fn ($query) => $query->where('crew_profile_id', $profile->id)])
            ->where('status', 'published')
            ->where(fn ($query) => $query
                ->whereNull('crew_role_id')
                ->orWhereIn('crew_role_id', $eligibleRoleIds)
                ->orWhereHas('enrolments', fn ($enrolments) => $enrolments->where('crew_profile_id', $profile->id)))
            ->orderByDesc('is_required')->orderBy('title')->get();
    }
}
