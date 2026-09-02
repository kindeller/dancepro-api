<?php

namespace App\Features\Crew\Models;

use App\Shared\Models\HasPublicUuid;
use Database\Factories\CrewRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['uuid', 'code', 'name', 'event_type', 'event_type_definition_id', 'is_active'])]
class CrewRole extends Model
{
    /** @use HasFactory<CrewRoleFactory> */
    use HasFactory, HasPublicUuid;

    public function crewProfiles(): BelongsToMany
    {
        return $this->belongsToMany(CrewProfile::class, 'crew_role_qualifications')
            ->using(CrewRoleQualification::class)
            ->withPivot(['status', 'effective_from', 'effective_until', 'notes'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CrewRoleFactory
    {
        return CrewRoleFactory::new();
    }
}
