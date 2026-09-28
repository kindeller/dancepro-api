<?php

namespace App\Features\Studios\Actions;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioEventAcknowledgement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStudioEventRequirements
{
    private const REQUIREMENTS = ['portrait_space', 'portrait_dancers'];

    public function execute(SchedulingEvent $event, User $user, array $completed): void
    {
        $event->loadMissing('concertBookingItem');
        if ($event->concertBookingItem?->item_type !== ConcertBookingItemType::DressRehearsal) {
            throw ValidationException::withMessages(['requirements' => 'Portrait shoot requirements apply only to dress rehearsal portrait events.']);
        }

        $completed = array_values(array_intersect(self::REQUIREMENTS, $completed));
        DB::transaction(function () use ($event, $user, $completed): void {
            StudioEventAcknowledgement::query()->where('scheduling_event_id', $event->id)
                ->whereIn('requirement', array_diff(self::REQUIREMENTS, $completed))->delete();
            foreach ($completed as $requirement) {
                StudioEventAcknowledgement::updateOrCreate(
                    ['scheduling_event_id' => $event->id, 'requirement' => $requirement],
                    ['completed_by_user_id' => $user->id, 'completed_at' => now()],
                );
            }
        });
    }
}
