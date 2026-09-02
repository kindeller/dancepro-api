<?php

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\Models\AssignmentEquipmentResponsibility;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EquipmentScheduleDetails
{
    private const ITEM_CODES = ['video_1', 'video_2', 'video_3', 'backdrop_1', 'backdrop_2'];

    public function execute(): array
    {
        $responsibilities = AssignmentEquipmentResponsibility::query()
            ->whereIn('item_code', self::ITEM_CODES)
            ->with(['assignment.crewProfile', 'assignment.shift.schedulingEvent.venue'])
            ->get();

        $detailsByResponsibility = [];
        foreach ($responsibilities->groupBy('item_code') as $items) {
            $uses = $items->groupBy(fn ($responsibility) => $responsibility->assignment->shift->id)
                ->map(fn (Collection $group): array => $this->usage($group))
                ->sortBy('starts_at')->values();
            foreach ($uses as $index => $use) {
                $details = $this->details($use, $uses->get($index - 1), $uses->get($index + 1), $uses, $index);
                foreach ($use['responsibilities'] as $responsibility) {
                    $detailsByResponsibility[$responsibility->id] = $details;
                }
            }
        }

        return $detailsByResponsibility;
    }

    private function usage(Collection $responsibilities): array
    {
        $first = $responsibilities->first();
        $shift = $first->assignment->shift;

        return [
            'shift' => $shift,
            'event' => $shift->schedulingEvent,
            'venue' => $shift->schedulingEvent->venue,
            'starts_at' => $shift->starts_at ?? Carbon::parse($shift->shift_date)->startOfDay(),
            'ends_at' => $shift->estimated_finish_at ?? Carbon::parse($shift->shift_date)->endOfDay(),
            'responsibilities' => $responsibilities,
            'bringers' => $responsibilities->where('is_bringing', true)->values(),
            'takers' => $responsibilities->where('is_taking', true)->values(),
            'notes' => $responsibilities->pluck('other_notes')->filter()->unique()->values(),
        ];
    }

    private function details(array $use, ?array $previous, ?array $next, Collection $uses, int $index): array
    {
        $warnings = collect();
        $continuity = collect();
        if ($use['takers']->count() > 1) {
            $warnings->push('More than one person is marked as taking this kit.');
        }
        foreach ($uses as $otherIndex => $other) {
            if ($index !== $otherIndex && $use['event']->id !== $other['event']->id && $use['starts_at']->lt($other['ends_at']) && $other['starts_at']->lt($use['ends_at'])) {
                $warnings->push('Clash: this kit is also scheduled at '.$other['event']->name.'.');
            }
        }

        if ($previous) {
            $previousTakers = $previous['takers']->pluck('assignment.crew_profile_id');
            $currentBringers = $use['bringers']->pluck('assignment.crew_profile_id');
            $hasTransferNote = $previous['notes']->isNotEmpty() || $use['notes']->isNotEmpty();
            if ($previousTakers->isEmpty() && $use['bringers']->isEmpty() && $previous['venue']?->id === $use['venue']?->id) {
                $continuity->push('Stays at this venue from '.$previous['event']->name.'.');
            } elseif ($previousTakers->isEmpty() && $use['bringers']->isEmpty() && ! $hasTransferNote) {
                $warnings->push('No transport is recorded from the previous event.');
            } elseif ($previousTakers->isNotEmpty() && $currentBringers->intersect($previousTakers)->isEmpty() && ! $hasTransferNote) {
                $warnings->push('The previous taker and this event’s bringer do not match. Add the transfer in Other.');
            } elseif ($hasTransferNote) {
                $continuity->push('A transfer or drop-off is explained in Other.');
            }
        }

        if ($next && $use['takers']->isEmpty() && $next['bringers']->isEmpty()) {
            if ($use['venue']?->id === $next['venue']?->id) {
                $continuity->push('Stays at this venue for '.$next['event']->name.'.');
            } elseif ($use['notes']->isEmpty() && $next['notes']->isEmpty()) {
                $warnings->push('No one is taking it and no transport is recorded for the next event.');
            }
        }

        return [
            'previous' => $previous ? $this->summary($previous) : null,
            'next' => $next ? $this->summary($next) : null,
            'warnings' => $warnings->unique()->values(),
            'continuity' => $continuity->unique()->values(),
        ];
    }

    private function summary(array $use): string
    {
        return $use['event']->name.' · '.$use['shift']->shift_date->format('D j M').' · '.($use['venue']?->name ?? 'Venue TBC');
    }
}
