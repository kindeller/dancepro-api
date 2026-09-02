<?php

namespace Database\Seeders;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Actions\SavePayRateVersion;
use Illuminate\Database\Seeder;
use RuntimeException;

class PaymentPlaceholderSeeder extends Seeder
{
    public function run(SavePayRateVersion $saveRate): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('PaymentPlaceholderSeeder may only run locally.');
        }

        $rates = [
            'competition_hourly' => [30, true],
            'competition_team_leader_hourly' => [35, true],
            'concert_fixed' => [250, true],
            'concert_hourly' => [30, true],
            'travel_allowance' => [60, false],
            'equipment_collection_allowance' => [15, false],
            'equipment_return_allowance' => [15, false],
        ];

        foreach ($rates as $rateKey => [$amount, $isSuperable]) {
            $saveRate->execute([
                'rate_key' => $rateKey,
                'amount' => $amount,
                'effective_from' => '2026-01-01',
                'is_superable' => $isSuperable,
            ]);
        }

        foreach (CrewProfile::query()->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('id')->get() as $crewProfile) {
            foreach ($rates as $rateKey => [$amount, $isSuperable]) {
                $saveRate->execute([
                    'crew_profile_id' => $crewProfile->id,
                    'rate_key' => $rateKey,
                    'amount' => $amount,
                    'effective_from' => '2026-01-01',
                    'is_superable' => $isSuperable,
                ]);
            }
        }

        $this->command?->warn('Fictional payment placeholder rates seeded for local interface development only.');
    }
}
