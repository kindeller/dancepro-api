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
            ->whereNull('cancelled_at')
            ->where('availability_status', AvailabilityRoundStatus::Open)
            ->where('availability_deadline', '>=', now())
            ->with(['venue', 'studio', 'competitionContact', 'shifts' => fn ($query) => $query->with([
                'availabilityResponses' => fn ($responses) => $responses->where('crew_profile_id', $profile->id),
                'assignments' => fn ($assignments) => $assignments->where('crew_profile_id', $profile->id)->where('status', 'published'),
            ])])
            ->orderBy('event_date')->get()
            ->flatMap(fn (SchedulingEvent $event) => $event->shifts
                ->filter(fn (SchedulingShift $shift): bool => $shift->assignments->isEmpty())
                ->map(fn (SchedulingShift $shift): array => $this->resource($shift, $shift->availabilityResponses->first(), $event)))
            ->values();
    }

    public function findFor(CrewProfile $profile, SchedulingShift $shift): array
    {
        $shift->loadMissing(['schedulingEvent.venue', 'schedulingEvent.studio', 'schedulingEvent.competitionContact']);
        $response = $shift->availabilityResponses()->where('crew_profile_id', $profile->id)->first();

        return $this->resource($shift, $response);
    }

    private function resource(SchedulingShift $shift, mixed $response, ?SchedulingEvent $event = null): array
    {
        $event ??= $shift->schedulingEvent;
        $organisation = $event->event_type->value === 'concert' ? $event->studio : $event->competitionContact;

        return [
            'shift_id' => $shift->uuid,
            'event_name' => $event->name,
            'event_type' => $event->event_type->value,
            'event_logo_url' => $event->crewLogoUrl(),
            'organisation' => $organisation ? [
                'id' => $organisation->uuid,
                'name' => $organisation->name,
            ] : null,
            'venue' => $event->venue ? [
                'id' => $event->venue->uuid,
                'name' => $event->venue->name,
            ] : null,
            'shift_period' => $shift->period?->value,
            'shift_date' => $shift->shift_date->toDateString(),
            'arrival_at' => $shift->posted_arrival_at?->toIso8601String(),
            'starts_at' => $shift->starts_at?->toIso8601String(),
            'finish_at' => $shift->estimated_finish_at?->toIso8601String(),
            'deadline' => $event->availability_deadline?->toIso8601String(),
            'status' => $response?->status?->value,
            'note' => $response?->note,
            'locked' => $response?->locked_at !== null,
            'allowed_actions' => $response?->locked_at === null
                ? array_values(array_filter(['update', $response ? 'clear' : null]))
                : [],
        ];
    }
}
