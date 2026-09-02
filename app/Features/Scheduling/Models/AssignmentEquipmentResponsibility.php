<?php

namespace App\Features\Scheduling\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_shift_assignment_id', 'item_code', 'is_bringing', 'is_taking', 'other_notes'])]
class AssignmentEquipmentResponsibility extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchedulingShiftAssignment::class, 'scheduling_shift_assignment_id');
    }

    protected function casts(): array
    {
        return ['is_bringing' => 'boolean', 'is_taking' => 'boolean'];
    }
}
