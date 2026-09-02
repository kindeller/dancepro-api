<?php

namespace App\Features\Timesheets\Models;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'crew_profile_id', 'scheduling_event_id', 'source', 'invoice_number', 'invoice_style', 'period_start', 'period_end', 'status', 'subtotal', 'allowance_total', 'total', 'superable_total', 'submitted_at', 'approved_at', 'approved_by_user_id', 'exported_at', 'paid_at'])]
class CrewInvoice extends Model
{
    use HasPublicUuid;

    public function crewProfile(): BelongsTo
    {
        return $this->belongsTo(CrewProfile::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function schedulingEvent(): BelongsTo
    {
        return $this->belongsTo(SchedulingEvent::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CrewInvoiceLine::class);
    }

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'exported_at' => 'datetime', 'paid_at' => 'datetime', 'subtotal' => 'decimal:2', 'allowance_total' => 'decimal:2', 'total' => 'decimal:2', 'superable_total' => 'decimal:2'];
    }
}
