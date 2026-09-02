<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\AvailabilityRoundStatus;
use Carbon\Carbon;

class UpdateAvailabilityRound
{
    public function execute(SchedulingEvent $event, array $data): SchedulingEvent
    {
        if ($data['availability_status'] === AvailabilityRoundStatus::Open->value) {
            $data['availability_deadline'] = Carbon::createFromFormat(
                'Y-m-d H:i',
                $data['availability_deadline'].' 17:00',
                config('app.timezone'),
            );
        }

        $event->update($data);

        return $event->refresh();
    }
}
