<?php

namespace App\Features\Timesheets\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Timesheets\Models\CrewInvoice;
use App\Features\Timesheets\Services\CrewInvoiceSelection;
use Illuminate\Support\Facades\DB;

class CreateSelectedCrewInvoice
{
    public function __construct(private readonly CrewInvoiceSelection $selection, private readonly AssignCrewInvoiceNumber $assignNumber) {}

    public function execute(CrewProfile $crew, array $entryIds, string $style, ?int $startingNumber): CrewInvoice
    {
        return DB::transaction(function () use ($crew, $entryIds, $style, $startingNumber): CrewInvoice {
            $selection = $this->selection->resolve($crew, $entryIds);
            $entries = $selection['entries'];
            $previews = $selection['previews'];
            $dates = $entries->pluck('assignment.shift.shift_date')->sort();
            $invoice = CrewInvoice::query()->create([
                'crew_profile_id' => $crew->id,
                'scheduling_event_id' => $selection['event']?->id,
                'source' => 'dancepro', 'invoice_style' => $style,
                'period_start' => $dates->first(), 'period_end' => $dates->last(),
                'status' => 'pending_payment', 'submitted_at' => now(),
                'subtotal' => $previews->sum('base'), 'allowance_total' => $previews->sum('allowanceTotal'),
                'total' => $previews->sum('total'), 'superable_total' => $previews->sum('superable'),
            ]);
            $this->assignNumber->execute($invoice, $startingNumber);

            foreach ($entries as $entry) {
                $preview = $previews[$entry->id];
                $assignment = $entry->assignment;
                $invoice->lines()->create([
                    'assignment_time_entry_id' => $entry->id,
                    'snapshot' => ['event' => $assignment->shift->schedulingEvent->name, 'date' => $assignment->shift->shift_date->toDateString(), 'role' => $assignment->role->name, 'start' => $preview['payableStart']?->format('g:i a'), 'finish' => $preview['payableFinish']?->format('g:i a'), 'hours' => $preview['hours'], 'rate' => $preview['rate']?->amount, 'rate_name' => $preview['rate']?->name],
                    'base_amount' => $preview['base'], 'allowance_amount' => $preview['allowanceTotal'], 'line_total' => $preview['total'],
                ]);
            }

            return $invoice->load(['crewProfile', 'lines', 'schedulingEvent']);
        });
    }
}
