<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeEventWorkflow
{
    public function __construct(private readonly UpdateAvailabilityRound $availability, private readonly PublishRoster $roster) {}

    public function execute(SchedulingEvent $event, array $input): void
    {
        DB::transaction(function () use ($event, $input): void {
            $event = SchedulingEvent::lockForUpdate()->findOrFail($event->id);
            if ($event->cancelled_at) {
                throw ValidationException::withMessages(['action' => 'This event is cancelled. Its status cannot be changed here.']);
            }
            if ($input['action'] === 'publish_roster') {
                if (! $event->shifts()->exists() || ! $event->roleRequirements()->exists()) {
                    throw ValidationException::withMessages(['action' => 'Add shifts and required roles before publishing the roster.']);
                }
                $this->roster->execute($event);
            } else {
                if ($input['action'] === 'draft' && $event->roster_status !== 'draft') {
                    throw ValidationException::withMessages(['action' => 'The roster has already been published. Draft cannot undo crew allocations or notifications.']);
                }
                $this->availability->execute($event, ['availability_status' => $input['action'], 'availability_deadline' => $input['deadline'] ?? null]);
            }
        });
    }
}
