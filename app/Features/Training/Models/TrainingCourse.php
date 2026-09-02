<?php

namespace App\Features\Training\Models;

use App\Features\Crew\Models\CrewRole;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'crew_role_id', 'renewal_of_course_id', 'title', 'description', 'estimated_minutes', 'status', 'is_required', 'grants_role_qualification'])]
class TrainingCourse extends Model
{
    use HasPublicUuid;

    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }

    public function renewalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewal_of_course_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TrainingModule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TrainingSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(TrainingEnrolment::class);
    }

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'grants_role_qualification' => 'boolean', 'estimated_minutes' => 'integer'];
    }
}
