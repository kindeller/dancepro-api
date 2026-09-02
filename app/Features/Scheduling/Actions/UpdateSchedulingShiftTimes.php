<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\SchedulingShift;
use App\Features\Scheduling\Support\ShiftPeriod;
use Carbon\Carbon;

class UpdateSchedulingShiftTimes
{
    public function __construct(private readonly ResetAssignmentAcknowledgements $resetAcknowledgements) {}

    public function execute(SchedulingShift $shift, string $startTime, string $finishTime): SchedulingShift
    {
        $startsAt = Carbon::parse($shift->shift_date->toDateString().' '.$startTime);
        $finishAt = Carbon::parse($shift->shift_date->toDateString().' '.$finishTime);
        $arrivalMinutes = $shift->schedulingEvent->event_type->value === 'concert' ? 90 : ($shift->requires_setup ? 90 : ($shift->period === ShiftPeriod::Morning ? 45 : 30));

        $shift->update([
            'posted_arrival_at' => $startsAt->copy()->subMinutes($arrivalMinutes),
            'starts_at' => $startsAt,
            'estimated_finish_at' => $finishAt->copy()->addMinutes($shift->requires_set_down ? 20 : 0),
        ]);

        if ($shift->wasChanged(['posted_arrival_at', 'starts_at', 'estimated_finish_at'])) {
            $this->resetAcknowledgements->execute($shift->schedulingEvent, 'The arrival or shift time changed');
        }

        return $shift->refresh();
    }
}
