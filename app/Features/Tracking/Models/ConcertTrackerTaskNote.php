<?php

namespace App\Features\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concert_tracker_task_id', 'user_id', 'body', 'follow_up_date'])]
class ConcertTrackerTaskNote extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(ConcertTrackerTask::class, 'concert_tracker_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }
}
