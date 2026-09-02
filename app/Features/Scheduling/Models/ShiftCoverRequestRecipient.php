<?php

namespace App\Features\Scheduling\Models;

use App\Features\Crew\Models\CrewProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shift_cover_request_id', 'crew_profile_id', 'status', 'responded_at'])]
class ShiftCoverRequestRecipient extends Model
{
    public function coverRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftCoverRequest::class, 'shift_cover_request_id');
    }

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }
}
