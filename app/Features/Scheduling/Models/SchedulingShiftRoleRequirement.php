<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_shift_id', 'crew_role_id', 'quantity'])]
class SchedulingShiftRoleRequirement extends Model
{
    public function shift(): BelongsTo
    {
        return $this->belongsTo(SchedulingShift::class, 'scheduling_shift_id');
    }

    public function crewRole(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class);
    }
}
