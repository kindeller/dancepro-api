<?php

namespace App\Features\Tracking\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'workflow_context', 'studio_invoice_id', 'photo_release_group_id', 'context_hash', 'invalidated_at', 'cancellation_reason', 'is_simulated', 'scheduling_event_id', 'concert_tracker_task_id', 'concert_tracker_email_template_id', 'created_by_user_id', 'recipient_name', 'recipient_email', 'subject', 'body', 'status', 'scheduled_at', 'queued_at', 'sent_at', 'failed_at', 'cancelled_at', 'cancelled_by_user_id', 'failure_message', 'provider_message_id', 'open_count', 'first_opened_at', 'last_opened_at'])]
class ConcertTrackerEmailDelivery extends Model
{
    use HasPublicUuid;

    public function task(): BelongsTo
    {
        return $this->belongsTo(ConcertTrackerTask::class, 'concert_tracker_task_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConcertTrackerEmailTemplate::class, 'concert_tracker_email_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    protected function casts(): array
    {
        return ['workflow_context' => 'array', 'invalidated_at' => 'datetime', 'is_simulated' => 'boolean', 'scheduled_at' => 'datetime', 'queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime', 'open_count' => 'integer', 'first_opened_at' => 'datetime', 'last_opened_at' => 'datetime'];
    }
}
