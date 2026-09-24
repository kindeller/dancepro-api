<?php

namespace App\Features\Tracking\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'user_id', 'scheduling_event_id', 'concert_tracker_task_id', 'type', 'dedupe_key', 'title', 'body', 'url', 'read_at', 'resolved_at', 'digest_sent_at'])]
class ConcertTrackerAlert extends Model
{
    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ConcertTrackerTask::class, 'concert_tracker_task_id');
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'resolved_at' => 'datetime', 'digest_sent_at' => 'datetime'];
    }
}
