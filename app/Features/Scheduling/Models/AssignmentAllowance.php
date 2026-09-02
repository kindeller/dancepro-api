<?php

namespace App\Features\Scheduling\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_shift_assignment_id', 'allowance_key', 'quantity'])]
class AssignmentAllowance extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchedulingShiftAssignment::class, 'scheduling_shift_assignment_id');
    }
}
