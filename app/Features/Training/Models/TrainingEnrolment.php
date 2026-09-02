<?php

namespace App\Features\Training\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['training_course_id', 'crew_profile_id', 'assigned_by_user_id', 'status', 'assigned_at', 'due_at', 'started_at', 'completed_at'])]
class TrainingEnrolment extends Model
{
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function moduleProgress(): HasMany
    {
        return $this->hasMany(TrainingModuleProgress::class);
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(TrainingAssessmentAttempt::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(TrainingReminder::class);
    }

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'due_at' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
