<?php

namespace App\Features\Notifications\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Illuminate\Support\Facades\DB;

class CreateDailyActionDigests
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            $pending = CrewNotification::query()
                ->whereIn('type', ['shift_allocation', 'shift_changed', 'availability_request'])
                ->whereNull('digest_notified_at')
                ->lockForUpdate()
                ->get();
            $actionableByUser = $pending
                ->filter(fn (CrewNotification $notification): bool => $this->isStillActionable($notification))
                ->groupBy('user_id');

            if ($pending->isNotEmpty()) {
                CrewNotification::query()->whereKey($pending->modelKeys())
                    ->update(['digest_notified_at' => now()]);
            }

            foreach ($actionableByUser as $userId => $notifications) {
                CrewNotification::query()->create([
                    'user_id' => $userId,
                    'type' => 'action_digest',
                    'title' => 'Crew Hub',
                    'message' => 'You have new items to action.',
                ]);
            }

            return $actionableByUser->count();
        });
    }

    private function isStillActionable(CrewNotification $notification): bool
    {
        if (in_array($notification->type, ['shift_allocation', 'shift_changed'], true)) {
            return SchedulingShiftAssignment::query()
                ->where('uuid', $notification->destination_id)
                ->where('status', 'published')
                ->where('acknowledgement_status', '!=', 'acknowledged')
                ->whereHas('crewProfile', fn ($query) => $query->where('user_id', $notification->user_id))
                ->whereHas('shift.schedulingEvent', fn ($query) => $query->whereNull('cancelled_at'))
                ->exists();
        }

        $profile = $notification->user?->crewProfile;
        if (! $profile) {
            return false;
        }

        return SchedulingEvent::query()
            ->where('uuid', $notification->destination_id)
            ->whereNull('cancelled_at')
            ->where('availability_status', AvailabilityRoundStatus::Open)
            ->where('availability_deadline', '>=', now())
            ->whereHas('shifts', fn ($query) => $query
                ->whereDoesntHave('assignments', fn ($assignment) => $assignment
                    ->where('crew_profile_id', $profile->id)
                    ->where('status', 'published'))
                ->whereDoesntHave('availabilityResponses', fn ($response) => $response
                    ->where('crew_profile_id', $profile->id)))
            ->exists();
    }
}
