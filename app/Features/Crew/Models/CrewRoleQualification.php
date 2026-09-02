<?php

namespace App\Features\Crew\Models;

use App\Features\Crew\Support\CrewRoleQualificationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CrewRoleQualification extends Pivot
{
    protected $table = 'crew_role_qualifications';

    protected $guarded = [];

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function crewRole(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CrewRoleQualificationStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }
}
