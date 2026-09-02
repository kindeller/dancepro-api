<?php

namespace App\Features\Crew\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'crew_profile_id', 'make', 'model', 'registration', 'colour', 'notes'])]
class CrewVehicle extends Model
{
    use HasPublicUuid;

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }
}
