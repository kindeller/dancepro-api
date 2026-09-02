<?php

namespace App\Features\Training\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_enrolment_id', 'recorded_by_user_id', 'method', 'note', 'reminded_at'])]
class TrainingReminder extends Model
{
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrolment::class, 'training_enrolment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return ['reminded_at' => 'datetime'];
    }
}
