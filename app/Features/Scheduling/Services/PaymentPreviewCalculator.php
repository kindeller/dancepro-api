<?php

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\Models\PayRate;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Support\PaymentRateCatalog;
use App\Features\Scheduling\Support\SchedulingEventType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentPreviewCalculator
{
    public function execute(SchedulingShiftAssignment $assignment): array
    {
        $assignment->loadMissing(['role', 'crewProfile', 'shift.schedulingEvent', 'timeEntry', 'allowances']);
        $date = $assignment->shift->shift_date ?? $assignment->shift->schedulingEvent->event_date;
        $rateKey = $this->rateKey($assignment);
        $rate = $rateKey ? $this->rate($rateKey, $date->toDateString(), $assignment->crew_profile_id) : null;
        $flags = collect();
        $hours = null;
        $base = null;
        $actualStart = $assignment->timeEntry?->actual_clock_in_at;
        $actualFinish = $assignment->timeEntry?->actual_finish_at;
        $payableStart = $actualStart ? $this->roundedPayableStart($assignment, $actualStart) : null;
        $payableFinish = $actualFinish ? $this->roundedPayableFinish($actualFinish) : null;
        if (! $rateKey) {
            $flags->push('No payment category is defined for this role.');
        } elseif (! $rate) {
            $flags->push('Rate not configured for this event date.');
        } elseif ($rate->calculation_type === 'hourly') {
            if (! $payableStart || ! $payableFinish) {
                $flags->push('Time entry incomplete.');
            } else {
                $minutes = max(0, $payableStart->diffInMinutes($payableFinish, false));
                $hours = round($minutes / 60, 2);
                $base = round($hours * (float) $rate->amount, 2);
            }
        } else {
            $base = (float) $rate->amount;
            if (! $payableStart || ! $payableFinish) {
                $flags->push('Actual arrival and finish still need to be recorded.');
            } elseif ($assignment->shift->schedulingEvent->event_type === SchedulingEventType::Concert && $payableStart->diffInMinutes($payableFinish, false) > 420) {
                $flags->push('Over 7 hours — manual calculation required.');
            }
        }

        $allowances = $assignment->allowances->whereIn(
            'allowance_key',
            array_keys(PaymentRateCatalog::allowancesForEvent($assignment->shift->schedulingEvent->event_type->value)),
        );
        $allowanceLines = $allowances->map(function ($allowance) use ($assignment, $date, $flags): array {
            $rate = $this->rate($allowance->allowance_key, $date->toDateString(), $assignment->crew_profile_id);
            if (! $rate) {
                $flags->push('An assigned allowance has no configured rate.');
            }

            return ['key' => $allowance->allowance_key, 'rate' => $rate, 'quantity' => $allowance->quantity, 'amount' => $rate ? round((float) $rate->amount * $allowance->quantity, 2) : null];
        });
        $allowanceTotal = $allowanceLines->sum(fn (array $line): float => $line['amount'] ?? 0);
        $total = $base === null ? null : round($base + $allowanceTotal, 2);
        $superable = round(($base !== null && $rate?->is_superable ? $base : 0) + $allowanceLines->sum(fn (array $line): float => $line['rate']?->is_superable ? ($line['amount'] ?? 0) : 0), 2);

        return compact('rateKey', 'rate', 'hours', 'base', 'allowanceLines', 'allowanceTotal', 'total', 'superable', 'flags', 'payableStart', 'payableFinish');
    }

    private function roundedPayableStart(SchedulingShiftAssignment $assignment, Carbon $actualStart): Carbon
    {
        $rounded = $actualStart->copy()->setTime(
            $actualStart->hour,
            intdiv($actualStart->minute, 15) * 15,
        );
        $boundary = $assignment->shift->posted_arrival_at ?? $assignment->shift->starts_at;

        return $boundary && $rounded->lt($boundary) ? $boundary->copy() : $rounded;
    }

    private function roundedPayableFinish(Carbon $actualFinish): Carbon
    {
        $rounded = $actualFinish->copy();
        if ($rounded->minute % 15 !== 0 || $rounded->second !== 0 || $rounded->micro !== 0) {
            $rounded->addMinutes(15 - ($rounded->minute % 15));
        }

        return $rounded->setTime($rounded->hour, $rounded->minute);
    }

    private function rateKey(SchedulingShiftAssignment $assignment): ?string
    {
        if ($assignment->shift->schedulingEvent->event_type === SchedulingEventType::Competition) {
            return $assignment->is_team_leader ? 'competition_team_leader_hourly' : 'competition_hourly';
        }

        return match ($assignment->role->code) {
            'concert-photographer-p2' => $this->isTrainee($assignment) ? 'concert_hourly' : 'concert_fixed',
            'concert-dr-portrait-assistant' => 'concert_hourly',
            'concert-videographer', 'concert-photographer-p1', 'photographer-p' => 'concert_fixed',
            default => null,
        };
    }

    private function isTrainee(SchedulingShiftAssignment $assignment): bool
    {
        return DB::table('crew_role_qualifications')->where('crew_profile_id', $assignment->crew_profile_id)
            ->where('crew_role_id', $assignment->crew_role_id)->value('status') === 'training';
    }

    private function rate(string $key, string $date, ?int $crewProfileId = null): ?PayRate
    {
        return PayRate::query()->where('rate_key', $key)->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->where(fn ($query) => $query->where('crew_profile_id', $crewProfileId)->orWhereNull('crew_profile_id'))
            ->orderByRaw('crew_profile_id is null')
            ->orderByDesc('effective_from')->first();
    }
}
