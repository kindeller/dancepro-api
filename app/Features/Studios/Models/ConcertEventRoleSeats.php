<?php

namespace App\Features\Studios\Models;

use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_event_id', 'crew_role_id', 'seats', 'submitted_by_user_id', 'submitted_at'])]
class ConcertEventRoleSeats extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function crewRole(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    protected function casts(): array
    {
        return ['seats' => 'array', 'submitted_at' => 'datetime'];
    }
}
