<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Programmes\Models\CompetitionGallerySetting;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterCustomerCompetition
{
    public function execute(User $user, string $eventUuid, string $loginCode): SchedulingEvent
    {
        abort_unless($user->canAccessCustomer(), 403);
        if (! CustomerDancer::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['login_code' => 'Add a dancer in My Profile first.']);
        }

        return DB::transaction(function () use ($eventUuid, $loginCode, $user): SchedulingEvent {
            // Share the event lock with gallery settings so a code change cannot race verification.
            $event = SchedulingEvent::where('uuid', $eventUuid)->where('event_type', 'competition')
                ->whereNull('cancelled_at')->lockForUpdate()->first();
            $hash = $event ? CompetitionGallerySetting::where('scheduling_event_id', $event->id)->value('login_code_hash') : null;
            if (! $hash || ! Hash::check(strtoupper(trim($loginCode)), $hash)) {
                throw ValidationException::withMessages(['login_code' => 'The event or login code is incorrect. Please check and try again.']);
            }
            CustomerCompetitionRegistration::updateOrCreate(
                ['user_id' => $user->id, 'scheduling_event_id' => $event->id],
                ['access_verified_at' => now()],
            );

            return $event;
        });
    }
}
