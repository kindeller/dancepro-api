<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Http\UploadedFile;

class SaveEventOperations
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function execute(SchedulingEvent $event, array $data): SchedulingEvent
    {
        $event->fill(collect($data)->except('programme')->all())->save();
        if (($data['programme'] ?? null) instanceof UploadedFile) {
            $event->programme_path = $this->files->store($data['programme'], "operations/events/{$event->uuid}");
            $event->save();
        }

        return $event->refresh();
    }
}
