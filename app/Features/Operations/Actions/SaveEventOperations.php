<?php

namespace App\Features\Operations\Actions;

use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Http\UploadedFile;

class SaveEventOperations
{
    public function execute(SchedulingEvent $event, array $data): SchedulingEvent
    {
        $event->fill(collect($data)->except('programme')->all())->save();
        if (($data['programme'] ?? null) instanceof UploadedFile) {
            $event->programme_path = $data['programme']->store("operations/events/{$event->uuid}", 'public');
            $event->save();
        }

        return $event->refresh();
    }
}
