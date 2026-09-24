<?php

namespace App\Features\Tracking\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'scheduling_event_id', 'reply_to_delivery_id', 'provider_message_id', 'sender_name', 'sender_email', 'recipient_email', 'subject', 'body', 'received_at'])]
class ConcertTrackerInboundEmail extends Model
{
    use HasPublicUuid;

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function replyToDelivery(): BelongsTo
    {
        return $this->belongsTo(ConcertTrackerEmailDelivery::class, 'reply_to_delivery_id');
    }

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
