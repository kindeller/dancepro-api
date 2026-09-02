<?php

namespace App\Features\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_enrolment_id', 'training_module_id', 'selected_option', 'passed', 'attempts', 'completed_at'])]
class TrainingModuleProgress extends Model
{
    protected $table = 'training_module_progress';

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
        return ['selected_option' => 'integer', 'passed' => 'boolean', 'completed_at' => 'datetime'];
    }
}
