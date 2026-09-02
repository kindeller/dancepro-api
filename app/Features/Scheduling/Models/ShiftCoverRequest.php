<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'scheduling_shift_assignment_id', 'requested_by_crew_profile_id', 'accepted_by_crew_profile_id', 'status', 'message', 'accepted_at'])]
class ShiftCoverRequest extends Model
{
    use HasPublicUuid;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchedulingShiftAssignment::class, 'scheduling_shift_assignment_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class, 'requested_by_crew_profile_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class, 'accepted_by_crew_profile_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ShiftCoverRequestRecipient::class);
    }

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
