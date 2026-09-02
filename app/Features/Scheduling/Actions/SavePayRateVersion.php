<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\PayRate;
use App\Features\Scheduling\Support\PaymentRateCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SavePayRateVersion
{
    public function execute(array $data): PayRate
    {
        return DB::transaction(function () use ($data): PayRate {
            [$name, $type, $defaultSuperable] = PaymentRateCatalog::all()[$data['rate_key']];
            $effectiveFrom = Carbon::parse($data['effective_from'])->startOfDay();
            $crewProfileId = $data['crew_profile_id'] ?? null;
            PayRate::query()->where('rate_key', $data['rate_key'])
                ->where('crew_profile_id', $crewProfileId)
                ->whereDate('effective_from', '<', $effectiveFrom)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $effectiveFrom))
                ->update(['effective_until' => $effectiveFrom->copy()->subDay()->toDateString()]);

            $nextVersion = PayRate::query()
                ->where('rate_key', $data['rate_key'])
                ->where('crew_profile_id', $crewProfileId)
                ->whereDate('effective_from', '>', $effectiveFrom)
                ->orderBy('effective_from')
                ->first();

            return PayRate::query()->updateOrCreate(
                ['crew_profile_id' => $crewProfileId, 'rate_key' => $data['rate_key'], 'effective_from' => $effectiveFrom->toDateString()],
                ['name' => $name, 'calculation_type' => $type, 'amount' => $data['amount'], 'is_superable' => $data['is_superable'] ?? $defaultSuperable, 'effective_until' => $nextVersion?->effective_from->copy()->subDay()->toDateString()],
            );
        });
    }
}
