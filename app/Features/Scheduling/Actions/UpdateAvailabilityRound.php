<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class UpdateAvailabilityRound
{
    public function execute(SchedulingEvent $event, array $data): SchedulingEvent
    {
        if ($event->cancelled_at !== null) {
            throw ValidationException::withMessages(['availability_status' => 'Availability cannot change for a cancelled event.']);
        }
        $wasOpen = $event->availability_status === AvailabilityRoundStatus::Open;
        if ($data['availability_status'] === AvailabilityRoundStatus::Open->value) {
            $data['availability_deadline'] = Carbon::createFromFormat(
                'Y-m-d H:i',
                $data['availability_deadline'].' 17:00',
                config('app.timezone'),
            );
        }

        $event->update($data);

        if (! $wasOpen && $event->availability_status === AvailabilityRoundStatus::Open) {
            User::query()->where('is_active', true)->whereHas('crewProfile')->each(
                fn (User $user) => CrewNotification::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => 'availability_request',
                        'destination_type' => 'availability',
                        'destination_id' => $event->uuid,
                    ],
                    [
                        'title' => 'Availability requested',
                        'message' => "Please provide your availability for {$event->name}.",
                    ],
                ),
            );
        }

        return $event->refresh();
    }
}
