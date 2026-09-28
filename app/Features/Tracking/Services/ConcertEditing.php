<?php

namespace App\Features\Tracking\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConcertEditing
{
    public function assignedTo(User $user): Collection
    {
        return SchedulingEvent::query()->where('event_type', 'concert')->whereNull('cancelled_at')
            ->whereIn('id', $this->tasks($user)->select('scope_id'))
            ->with('studio')->orderByDesc('event_date')->get();
    }

    public function canEdit(User $user, SchedulingEvent $event): bool
    {
        return $user->canAccessCrew()
            && $event->event_type->value === 'concert' && $event->cancelled_at === null
            && $this->tasks($user)->where('scope_id', $event->id)->exists();
    }

    private function tasks(User $user): Builder
    {
        return ConcertTrackerTask::query()->where('scope_type', 'event')
            ->where('task_key', 'concert_edited')->where('assignee_user_id', $user->id);
    }
}
