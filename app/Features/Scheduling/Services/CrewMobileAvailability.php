<?php

namespace App\Features\Scheduling\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Illuminate\Support\Collection;

class CrewMobileAvailability
{
    public function for(CrewProfile $profile): Collection
    {
        return SchedulingEvent::query()
            ->where('availability_status', AvailabilityRoundStatus::Open)
            ->where('availability_deadline', '>=', now())
            ->with(['shifts' => fn ($query) => $query->with([
                'availabilityResponses' => fn ($responses) => $responses->where('crew_profile_id', $profile->id),
                'assignments' => fn ($assignments) => $assignments->where('crew_profile_id', $profile->id)->where('status', 'published'),
            ])])
            ->orderBy('event_date')->get()
            ->flatMap(fn (SchedulingEvent $event) => $event->shifts
                ->filter(fn (SchedulingShift $shift): bool => $shift->assignments->isEmpty())
                ->map(fn (SchedulingShift $shift): array => $this->resource($shift, $shift->availabilityResponses->first())))
            ->values();
    }

    public function findFor(CrewProfile $profile, SchedulingShift $shift): array
    {
        $shift->loadMissing('schedulingEvent');
        $response = $shift->availabilityResponses()->where('crew_profile_id', $profile->id)->first();

        return $this->resource($shift, $response);
    }

    private function resource(SchedulingShift $shift, mixed $response): array
    {
        return [
            'shift_id' => $shift->uuid,
            'event_name' => $shift->schedulingEvent->name,
            'shift_date' => $shift->shift_date->toDateString(),
            'deadline' => $shift->schedulingEvent->availability_deadline?->toIso8601String(),
            'status' => $response?->status?->value,
            'note' => $response?->note,
            'locked' => $response?->locked_at !== null,
            'allowed_actions' => $response?->locked_at === null ? ['update'] : [],
        ];
    }
}
