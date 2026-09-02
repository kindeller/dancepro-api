<?php

namespace App\Features\Scheduling\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_time_entry_id', 'changed_by_user_id', 'field', 'old_value', 'new_value', 'optional_note'])]
class AssignmentTimeEntryAudit extends Model
{
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(AssignmentTimeEntry::class, 'assignment_time_entry_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    protected function casts(): array
    {
        return ['old_value' => 'datetime', 'new_value' => 'datetime'];
    }
}
