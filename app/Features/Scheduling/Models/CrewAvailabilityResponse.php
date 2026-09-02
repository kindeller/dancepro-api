<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Support\AvailabilityResponseStatus;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'scheduling_shift_id', 'crew_profile_id', 'status', 'note', 'responded_at', 'locked_at'])]
class CrewAvailabilityResponse extends Model
{
    use HasPublicUuid;

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SchedulingShift::class, 'scheduling_shift_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    protected function casts(): array
    {
        return [
            'status' => AvailabilityResponseStatus::class,
            'responded_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
