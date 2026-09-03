<?php

namespace App\Features\Timesheets\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Services\PaymentPreviewCalculator;
use App\Features\Timesheets\Models\CrewInvoice;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class CrewMobileFinancials
{
    public function __construct(private readonly PaymentPreviewCalculator $calculator) {}

    public function timesheets(CrewProfile $profile): Collection
    {
        return SchedulingShiftAssignment::query()
            ->where('crew_profile_id', $profile->id)->where('status', 'published')
            ->whereHas('shift', fn ($query) => $query->whereDate('shift_date', '<=', today()))
            ->with(['shift.schedulingEvent', 'role', 'allowances', 'timeEntry.invoiceLine'])
            ->get()->sortByDesc(fn ($assignment) => $assignment->shift->shift_date)
            ->map(fn (SchedulingShiftAssignment $assignment): array => $this->timesheetResource($assignment))->values();
    }

    public function invoices(CrewProfile $profile, int $limit): CursorPaginator
    {
        return CrewInvoice::query()->where('crew_profile_id', $profile->id)->latest('id')->cursorPaginate($limit);
    }

    public function invoice(CrewProfile $profile, CrewInvoice $invoice): CrewInvoice
    {
        return CrewInvoice::query()->where('crew_profile_id', $profile->id)->whereKey($invoice->id)
            ->with(['lines', 'schedulingEvent'])->firstOrFail();
    }

    public function invoiceSummary(CrewInvoice $invoice): array
    {
        return [
            'id' => $invoice->uuid,
            'invoice_number' => $invoice->invoice_number !== null ? (string) $invoice->invoice_number : null,
            'status' => $invoice->status,
            'total' => $this->money($invoice->total),
            'submitted_at' => $invoice->submitted_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }

    public function invoiceDetail(CrewInvoice $invoice): array
    {
        $snapshot = $invoice->issuer_snapshot ?? [];

        return [...$this->invoiceSummary($invoice),
            'lines' => $invoice->lines->map(fn ($line): array => [
                'event' => $line->snapshot['event'] ?? null,
                'date' => $line->snapshot['date'] ?? null,
                'role' => $line->snapshot['role'] ?? null,
                'start' => $line->snapshot['start'] ?? null,
                'finish' => $line->snapshot['finish'] ?? null,
                'hours' => $line->snapshot['hours'] ?? null,
                'rate' => $line->snapshot['rate'] ?? null,
                'rate_name' => $line->snapshot['rate_name'] ?? null,
                'base_amount' => $this->money($line->base_amount),
                'allowance_amount' => $this->money($line->allowance_amount),
                'line_total' => $this->money($line->line_total),
            ])->values(),
            'payment_details' => [
                'account_name' => $snapshot['bank_account_name'] ?? null,
                'bank_name' => $snapshot['bank_name'] ?? null,
                'bsb_last_four' => $this->lastFour($snapshot['bank_bsb'] ?? null),
                'account_number_last_four' => $this->lastFour($snapshot['bank_account_number'] ?? null),
                'complete' => collect(['bank_account_name', 'bank_bsb', 'bank_account_number'])->every(fn ($key) => filled($snapshot[$key] ?? null)),
            ],
        ];
    }

    private function timesheetResource(SchedulingShiftAssignment $assignment): array
    {
        $entry = $assignment->timeEntry;
        $preview = $this->calculator->execute($assignment);
        $status = $entry?->invoiceLine ? 'invoiced' : match (true) {
            $entry?->approval_status === 'externally_invoiced' => 'externally_invoiced',
            $entry?->actual_clock_in_at !== null && $entry?->actual_finish_at !== null => 'ready_to_invoice',
            default => 'draft',
        };

        return [
            'id' => $assignment->uuid,
            'assignment_id' => $assignment->uuid,
            'event_name' => $assignment->shift->schedulingEvent->name,
            'event_date' => $assignment->shift->shift_date->toDateString(),
            'status' => $status,
            'actual_clock_in_at' => $entry?->actual_clock_in_at?->toIso8601String(),
            'actual_finish_at' => $entry?->actual_finish_at?->toIso8601String(),
            'locked' => $entry?->locked_at !== null,
            'total' => $preview['total'] !== null ? $this->money($preview['total']) : null,
        ];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function lastFour(?string $value): ?string
    {
        $normalised = preg_replace('/\s|-/', '', (string) $value);

        return filled($normalised) ? substr($normalised, -4) : null;
    }
}
