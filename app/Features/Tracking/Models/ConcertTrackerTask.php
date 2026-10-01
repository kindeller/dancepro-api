<?php

namespace App\Features\Tracking\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'scope_type', 'scope_id', 'task_key', 'status', 'status_override', 'follow_up_status', 'assignee_user_id', 'due_date_override', 'follow_up_date', 'completed_by_user_id', 'completed_at'])]
class ConcertTrackerTask extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if ($task->completed_at && $task->completed_by_user_id && ! $task->assignee_user_id) {
                $task->assignee_user_id = $task->completed_by_user_id;
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConcertTrackerTaskNote::class)->latest();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ConcertTrackerEmailDelivery::class)->latest();
    }

    protected function casts(): array
    {
        return [
            'quality_checks' => 'array',
            'due_date_override' => 'date',
            'follow_up_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
