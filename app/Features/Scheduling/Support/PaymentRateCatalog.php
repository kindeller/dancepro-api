<?php

namespace App\Features\Scheduling\Support;

final class PaymentRateCatalog
{
    public static function all(): array
    {
        return [
            'competition_hourly' => ['Competition rate', 'hourly', true],
            'competition_team_leader_hourly' => ['Competition Team Leader rate', 'hourly', true],
            'concert_fixed' => ['Concert flat rate', 'fixed', true],
            'concert_hourly' => ['Concert hourly rate', 'hourly', true],
            'travel_allowance' => ['Out-of-metro travel allowance', 'allowance', false],
            'equipment_collection_allowance' => ['Competition equipment pickup', 'allowance', false],
            'equipment_return_allowance' => ['Competition equipment drop-off', 'allowance', false],
        ];
    }

    public static function matrix(): array
    {
        return [
            'competition_hourly' => ['Competition rate', 'hourly', ['competition_hourly']],
            'competition_team_leader_hourly' => ['Competition Team Leader rate', 'hourly', ['competition_team_leader_hourly']],
            'competition_equipment_movement' => ['Competition pickup / drop-off', 'allowance', ['equipment_collection_allowance', 'equipment_return_allowance']],
            'concert_fixed' => ['Concert flat rate', 'fixed', ['concert_fixed']],
            'concert_hourly' => ['Concert hourly rate', 'hourly', ['concert_hourly']],
            'travel_allowance' => ['Out-of-metro travel allowance', 'allowance', ['travel_allowance']],
        ];
    }

    public static function allowances(): array
    {
        return array_intersect_key(self::all(), array_flip(['travel_allowance', 'equipment_collection_allowance', 'equipment_return_allowance']));
    }

    public static function allowancesForEvent(string $eventType): array
    {
        $keys = $eventType === 'competition'
            ? ['travel_allowance', 'equipment_collection_allowance', 'equipment_return_allowance']
            : ['travel_allowance'];

        return array_intersect_key(self::all(), array_flip($keys));
    }
}
