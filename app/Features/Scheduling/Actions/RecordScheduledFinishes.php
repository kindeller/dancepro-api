<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\AssignmentTimeEntry;
use Illuminate\Database\Eloquent\Builder;

class RecordScheduledFinishes
{
    public function execute(): int
    {
        $updated = 0;
        $this->eligible()->with('assignment.shift')->chunkById(200, function ($entries) use (&$updated): void {
            foreach ($entries as $entry) {
                $finish = $entry->assignment->shift->estimated_finish_at;
                // Repeat eligibility on the write so a concurrent clock-out is preserved.
                $updated += $this->eligible()->whereKey($entry->id)
                    ->where('actual_clock_in_at', '<=', $finish)
                    ->whereHas('assignment.shift', fn ($query) => $query->where('estimated_finish_at', $finish))
                    ->update([
                        'actual_finish_at' => $finish,
                        'finish_recorded_at' => now(),
                        'finish_source' => 'scheduled',
                    ]);
            }
        });

        return $updated;
    }

    private function eligible(): Builder
    {
        return AssignmentTimeEntry::query()
            ->whereNotNull('actual_clock_in_at')
            ->whereNull('actual_finish_at')
            ->whereNull('locked_at')
            ->where('approval_status', '!=', 'externally_invoiced')
            ->whereDoesntHave('invoiceLine')
            ->whereHas('assignment', fn ($query) => $query->where('status', 'published'))
            ->whereHas('assignment.shift.schedulingEvent', fn ($query) => $query->whereNull('cancelled_at'))
            ->whereHas('assignment.shift', fn ($query) => $query->whereNotNull('estimated_finish_at')
                ->where('estimated_finish_at', '<=', now()->subHours(2)));
    }
}
