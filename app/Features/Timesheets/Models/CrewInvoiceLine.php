<?php

namespace App\Features\Timesheets\Models;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crew_invoice_id', 'assignment_time_entry_id', 'snapshot', 'base_amount', 'allowance_amount', 'line_total'])]
class CrewInvoiceLine extends Model
{
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CrewInvoice::class, 'crew_invoice_id');
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(AssignmentTimeEntry::class, 'assignment_time_entry_id');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'base_amount' => 'decimal:2', 'allowance_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
