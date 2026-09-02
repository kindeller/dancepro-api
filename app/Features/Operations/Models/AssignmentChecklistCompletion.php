<?php

namespace App\Features\Operations\Models;

use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduling_shift_assignment_id', 'checklist_template_item_id', 'completed_by_user_id', 'completed_at'])]
class AssignmentChecklistCompletion extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchedulingShiftAssignment::class, 'scheduling_shift_assignment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateItem::class, 'checklist_template_item_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
