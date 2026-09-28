<?php

namespace App\Features\Programmes\Policies;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\ConcertEditing;
use App\Models\User;

class ProgrammePolicy
{
    public function view(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessAdmin() || app(ConcertEditing::class)->canEdit($user, $event) || ($user->canAccessCrew()
            && $event->shifts()->whereHas('assignments', fn ($query) => $query
                ->where('crew_profile_id', $user->crewProfile->id)->where('status', 'published'))->exists());
    }

    public function upload(User $user, SchedulingEvent $event): bool
    {
        return $event->cancelled_at === null && ($user->canAccessAdmin() || ($user->canAccessCrew()
            && $event->shifts()->whereHas('assignments', fn ($query) => $query
                ->where('crew_profile_id', $user->crewProfile->id)->where('status', 'published'))->exists()));
    }

    public function update(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessAdmin() && $event->cancelled_at === null;
    }
}
