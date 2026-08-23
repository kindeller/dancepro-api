<?php

namespace Database\Factories;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Support\ConcertAccessGrantSource;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConcertAccessGrant> */
class ConcertAccessGrantFactory extends Factory
{
    protected $model = ConcertAccessGrant::class;

    public function definition(): array
    {
        return [
            'concert_id' => Concert::factory(),
            'email' => fake()->safeEmail(),
            'source' => ConcertAccessGrantSource::Invitation,
            'status' => ConcertAccessGrantStatus::Active,
            'expires_at' => now()->addMonth(),
            'metadata' => ['seeded' => false],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ConcertAccessGrantStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
