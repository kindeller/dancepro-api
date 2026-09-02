<?php

namespace App\Features\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_enrolment_id', 'training_module_id', 'attempt_number', 'score_percent', 'passed', 'answers', 'results', 'submitted_at'])]
class TrainingAssessmentAttempt extends Model
{
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrolment::class, 'training_enrolment_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    protected function casts(): array
    {
        return ['score_percent' => 'decimal:2', 'passed' => 'boolean', 'answers' => 'array', 'results' => 'array', 'submitted_at' => 'datetime'];
    }
}
