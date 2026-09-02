<?php

namespace App\Features\Scheduling\Models;

use App\Features\Timesheets\Models\CrewInvoiceLine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['scheduling_shift_assignment_id', 'actual_clock_in_at', 'clock_in_recorded_at', 'clock_in_source', 'payable_start_at', 'actual_finish_at', 'finish_recorded_at', 'finish_source', 'optional_note', 'approval_status', 'submitted_at', 'approved_at', 'approved_by_user_id', 'return_note', 'locked_at'])]
class AssignmentTimeEntry extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SchedulingShiftAssignment::class, 'scheduling_shift_assignment_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AssignmentTimeEntryAudit::class);
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(CrewInvoiceLine::class);
    }

    public function reviewFlags(): array
    {
        $this->loadMissing(['assignment.shift', 'audits']);
        $flags = [];
        $shift = $this->assignment->shift;
        if ($this->actual_clock_in_at && $shift->posted_arrival_at && $this->actual_clock_in_at->gt($shift->posted_arrival_at)) {
            $flags[] = 'Clocked in after posted arrival';
        }
        if ($this->actual_clock_in_at && ! $this->actual_finish_at && $shift->estimated_finish_at?->isPast()) {
            $flags[] = 'Finish time missing';
        }
        if ($this->audits->isNotEmpty()) {
            $flags[] = 'Time corrected';
        }

        return $flags;
    }

    protected function casts(): array
    {
        return [
            'actual_clock_in_at' => 'datetime', 'clock_in_recorded_at' => 'datetime', 'payable_start_at' => 'datetime',
            'actual_finish_at' => 'datetime', 'finish_recorded_at' => 'datetime',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'locked_at' => 'datetime',
        ];
    }
}
