<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\AssignmentChecklistCompletion;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['uuid', 'scheduling_shift_id', 'crew_role_id', 'crew_profile_id', 'is_team_leader', 'status', 'acknowledgement_status', 'published_at', 'notified_at', 'acknowledged_at'])]
class SchedulingShiftAssignment extends Model
{
    use HasPublicUuid;

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SchedulingShift::class, 'scheduling_shift_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function equipmentResponsibilities(): HasMany
    {
        return $this->hasMany(AssignmentEquipmentResponsibility::class);
    }

    public function checklistCompletions(): HasMany
    {
        return $this->hasMany(AssignmentChecklistCompletion::class);
    }

    public function timeEntry(): HasOne
    {
        return $this->hasOne(AssignmentTimeEntry::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(AssignmentAllowance::class);
    }

    public function coverRequests(): HasMany
    {
        return $this->hasMany(ShiftCoverRequest::class);
    }

    protected function casts(): array
    {
        return ['is_team_leader' => 'boolean', 'published_at' => 'datetime', 'notified_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }
}
