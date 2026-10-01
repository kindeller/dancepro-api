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
    private const REQUIREMENTS = [
        'portrait' => ['portrait_space', 'portrait_dancers'],
        'event_details' => ['event_details'],
    ];

    public function execute(SchedulingEvent $event, User $user, array $completed, string $group = 'portrait'): void
    {
        $event->loadMissing('concertBookingItem');
        if ($group === 'portrait' && $event->concertBookingItem?->item_type !== ConcertBookingItemType::DressRehearsal) {
            throw ValidationException::withMessages(['requirements' => 'Portrait shoot requirements apply only to dress rehearsal portrait events.']);
        }

        $requirements = self::REQUIREMENTS[$group] ?? throw ValidationException::withMessages(['group' => 'Unknown requirement group.']);
        if (array_diff($completed, $requirements)) {
            throw ValidationException::withMessages(['requirements' => 'Choose requirements from the selected group.']);
        }
        $completed = array_values(array_intersect($requirements, $completed));
        DB::transaction(function () use ($event, $user, $completed, $requirements): void {
            StudioEventAcknowledgement::query()->where('scheduling_event_id', $event->id)
                ->whereIn('requirement', array_diff($requirements, $completed))->delete();
            foreach ($completed as $requirement) {
                StudioEventAcknowledgement::updateOrCreate(
                    ['scheduling_event_id' => $event->id, 'requirement' => $requirement],
                    ['completed_by_user_id' => $user->id, 'completed_at' => now()],
                );
            }
        });
    }
}
