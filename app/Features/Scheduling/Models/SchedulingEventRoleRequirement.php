<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_event_id', 'crew_role_id', 'quantity'])]
class SchedulingEventRoleRequirement extends Model
{
    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function crewRole(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class);
    }
}
