<?php

namespace Database\Factories;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Studios\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Concert> */
class ConcertFactory extends Factory
{
    protected $model = Concert::class;

    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'name' => fake()->words(3, true),
            'status' => ConcertStatus::Draft,
            'is_enabled' => true,
            'event_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'storage_disk' => 's3_concerts',
            'storage_prefix' => 'studios/'.fake()->uuid().'/concerts/'.fake()->uuid().'/',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ConcertStatus::Published,
            'published_at' => now(),
            'is_enabled' => true,
        ]);
    }

    public function awaitingApproval(): static
    {
        return $this->published()->state(fn () => [
            'requires_approval' => true,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->published()->state(fn () => [
            'available_from' => now()->subMonth(),
            'available_until' => now()->subDay(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    public function passwordProtected(string $password = 'concert-demo'): static
    {
        return $this->state(fn () => ['access_password_hash' => $password]);
    }
}
