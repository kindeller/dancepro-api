<?php

namespace App\Features\Scheduling\Models;

use App\Features\Scheduling\Support\ShiftPeriod;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'scheduling_event_id', 'period', 'shift_date', 'requires_setup', 'requires_set_down', 'posted_arrival_at', 'starts_at', 'estimated_finish_at'])]
class SchedulingShift extends Model
{
    use HasPublicUuid;

    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function availabilityResponses(): HasMany
    {
        return $this->hasMany(CrewAvailabilityResponse::class);
    }

    public function roleRequirements(): HasMany
    {
        return $this->hasMany(SchedulingShiftRoleRequirement::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SchedulingShiftAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'period' => ShiftPeriod::class,
            'shift_date' => 'date',
            'requires_setup' => 'boolean',
            'requires_set_down' => 'boolean',
            'posted_arrival_at' => 'datetime',
            'starts_at' => 'datetime',
            'estimated_finish_at' => 'datetime',
        ];
    }
}
