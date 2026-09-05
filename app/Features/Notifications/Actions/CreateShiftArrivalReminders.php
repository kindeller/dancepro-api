<?php

namespace App\Features\Notifications\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;

class CreateShiftArrivalReminders
{
    public function execute(): int
    {
        $arrivalMinute = now()->addHour()->startOfMinute();
        $created = 0;

        SchedulingShiftAssignment::query()
            ->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query
                ->whereBetween('posted_arrival_at', [
                    $arrivalMinute,
                    $arrivalMinute->copy()->endOfMinute(),
                ])
                ->whereHas('schedulingEvent', fn ($event) => $event->whereNull('cancelled_at')))
            ->with(['crewProfile', 'shift.schedulingEvent.venue'])
            ->chunkById(200, function ($assignments) use (&$created): void {
                foreach ($assignments as $assignment) {
                    $userId = $assignment->crewProfile?->user_id;
                    if (! $userId) {
                        continue;
                    }

                    $event = $assignment->shift->schedulingEvent;
                    $venue = $event->venue?->name ?? 'the scheduled venue';
                    $notification = CrewNotification::query()->firstOrCreate(
                        [
                            'user_id' => $userId,
                            'type' => 'shift_arrival_reminder',
                            'destination_type' => 'assignment',
                            'destination_id' => $assignment->uuid,
                        ],
                        [
                            'title' => 'Shift starts in 1 hour',
                            'message' => "Your {$event->name} shift starts in 1 hour at {$venue}.",
                        ],
                    );

                    if ($notification->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
