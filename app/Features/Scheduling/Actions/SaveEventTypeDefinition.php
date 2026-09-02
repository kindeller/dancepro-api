<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\EventTypeDefinition;

class SaveEventTypeDefinition
{
    public function execute(array $data, ?EventTypeDefinition $eventType = null): EventTypeDefinition
    {
        $eventType ??= new EventTypeDefinition;
        $eventType->fill($data);
        $eventType->save();

        return $eventType;
    }
}
