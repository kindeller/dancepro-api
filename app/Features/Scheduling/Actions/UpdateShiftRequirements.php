<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingShift;
use Illuminate\Support\Facades\DB;

class UpdateShiftRequirements
{
    public function execute(SchedulingShift $shift, bool $setup, bool $setDown): void
    {
        DB::transaction(function () use ($shift, $setup, $setDown): void {
            $shift = SchedulingShift::query()->lockForUpdate()->findOrFail($shift->id);
            $shift->update(['requires_setup' => $setup, 'requires_set_down' => $setDown]);
            if (! $shift->wasChanged(['requires_setup', 'requires_set_down'])) {
                return;
            }

            $assignments = $shift->assignments()->with('crewProfile')->where('status', 'published')->get();
            foreach ($assignments as $assignment) {
                $assignment->update(['acknowledgement_status' => 'reset_by_material_change', 'acknowledged_at' => null, 'notified_at' => now()]);
                CrewNotification::query()->create([
                    'user_id' => $assignment->crewProfile->user_id,
                    'type' => 'shift_changed',
                    'title' => 'Setup / set down requirements updated',
                    'message' => "{$shift->schedulingEvent->name}: setup or set down requirements changed. Please review and acknowledge your shift again.",
                    'destination_type' => 'assignment',
                    'destination_id' => $assignment->uuid,
                ]);
            }
            if ($assignments->isNotEmpty()) {
                $shift->schedulingEvent->update(['roster_status' => 'changed']);
            }
        });
    }
}
