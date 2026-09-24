<?php

namespace App\Features\Studios\Models;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'scheduling_event_id', 'kind', 'status', 'is_current', 'disk', 'storage_key', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by_user_id', 'uploaded_at', 'reviewed_by_user_id', 'reviewed_at', 'review_note'])]
class StudioEventFile extends Model
{
    use HasPublicUuid;

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class, 'scheduling_event_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'uploaded_at' => 'datetime', 'reviewed_at' => 'datetime', 'size_bytes' => 'integer'];
    }
}
