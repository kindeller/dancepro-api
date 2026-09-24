<?php

namespace App\Features\Programmes\Policies;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class ProgrammePolicy
{
    public function view(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessAdmin() || ($user->canAccessCrew()
            && $event->shifts()->whereHas('assignments', fn ($query) => $query
                ->where('crew_profile_id', $user->crewProfile->id)->where('status', 'published'))->exists());
    }

    public function upload(User $user, SchedulingEvent $event): bool
    {
        return $this->view($user, $event) && $event->cancelled_at === null;
    }

    public function update(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessAdmin() && $event->cancelled_at === null;
    }
}
