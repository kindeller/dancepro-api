<?php

namespace App\Features\Training\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'training_course_id', 'training_section_id', 'title', 'module_type', 'content', 'video_url', 'quiz_question', 'quiz_options', 'correct_option', 'settings', 'sort_order'])]
class TrainingModule extends Model
{
    use HasPublicUuid;

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TrainingSection::class, 'training_section_id');
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(TrainingAssessmentAttempt::class);
    }

    protected function casts(): array
    {
        return ['quiz_options' => 'array', 'settings' => 'array', 'correct_option' => 'integer'];
    }
}
