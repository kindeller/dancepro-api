<?php

namespace App\Features\Scheduling\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'crew_profile_id', 'rate_key', 'name', 'calculation_type', 'amount', 'is_superable', 'effective_from', 'effective_until'])]
class PayRate extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_superable' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date'];
    }
}
