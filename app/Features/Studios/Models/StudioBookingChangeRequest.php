<?php

namespace App\Features\Studios\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'scheduling_event_id', 'requested_by_user_id', 'change_type', 'requested_value', 'details', 'status', 'resolved_by_user_id', 'resolved_at', 'requested_fields', 'resolution_note'])]
class StudioBookingChangeRequest extends Model
{
    use HasPublicUuid;

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'requested_fields' => 'array'];
    }
}
