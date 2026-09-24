<?php

namespace App\Features\Tracking\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'studio_id', 'scheduling_event_id', 'sender_user_id', 'direction', 'channel', 'sender_name', 'sender_email', 'recipient_email', 'subject', 'body', 'sent_at', 'staff_read_at', 'studio_read_at'])]
class StudioConversationMessage extends Model
{
    use HasPublicUuid;

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StudioConversationAttachment::class);
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'staff_read_at' => 'datetime', 'studio_read_at' => 'datetime'];
    }
}
