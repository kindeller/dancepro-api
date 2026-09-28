<?php

namespace App\Features\Studios\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'scheduling_event_id', 'requirement', 'completed_by_user_id', 'completed_at'])]
class StudioEventAcknowledgement extends Model
{
    use HasPublicUuid;

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
