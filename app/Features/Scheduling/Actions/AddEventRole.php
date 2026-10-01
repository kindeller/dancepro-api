<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EventRoleOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddEventRole
{
    public function __construct(private readonly EventRoleOptions $options) {}

    public function execute(SchedulingEvent $event, string $roleUuid): void
    {
        DB::transaction(function () use ($event, $roleUuid): void {
            $event = SchedulingEvent::lockForUpdate()->findOrFail($event->id);
            if ($event->cancelled_at) {
                throw ValidationException::withMessages(['role_uuid' => 'Roles cannot be added to a cancelled event.']);
            }
            $role = CrewRole::where('uuid', $roleUuid)->firstOrFail();
            if ($event->roleRequirements()->where('crew_role_id', $role->id)->exists()) {
                return;
            }
            if ($event->roleRequirements()->count() >= 3) {
                throw ValidationException::withMessages(['role_uuid' => 'All three role slots are filled.']);
            }
            if (! $this->options->forEvent($event)->contains('id', $role->id)) {
                throw ValidationException::withMessages(['role_uuid' => 'Choose an active role applicable to this event.']);
            }
            $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
            if ($event->roster_status !== 'draft') {
                $event->update(['roster_status' => 'changed']);
            }
        });
    }
}
