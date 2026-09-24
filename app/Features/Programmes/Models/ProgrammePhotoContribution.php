<?php

namespace App\Features\Programmes\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammePhotoContribution extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }

    protected function casts(): array
    {
        return ['assignment_uuids' => 'array', 'completed_at' => 'datetime', 'waived_at' => 'datetime'];
    }
}
