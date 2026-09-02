<?php

namespace App\Features\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['training_course_id', 'title', 'description', 'sort_order'])]
class TrainingSection extends Model
{
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TrainingModule::class)->orderBy('sort_order')->orderBy('id');
    }
}
