<?php

namespace App\Features\Operations\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'scheduling_event_id', 'author_user_id', 'message_type', 'body', 'attachment_path', 'attachment_name', 'attachment_mime'])]
class EventMessage extends Model
{
    use HasPublicUuid;

    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(EventMessageRead::class);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? route('internal-documents.messages.attachment', $this) : null;
    }

    public function isAnnouncement(): bool
    {
        return $this->message_type === 'announcement';
    }
}
