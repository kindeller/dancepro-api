<?php

namespace Database\Factories;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Support\CrewContractStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewContract> */
class CrewContractFactory extends Factory
{
    protected $model = CrewContract::class;

    public function definition(): array
    {
        return [
            'name' => 'Crew Services Agreement',
            'version' => fake()->unique()->numerify('20##.##'),
            'status' => CrewContractStatus::Draft,
            'effective_from' => fake()->dateTimeBetween('-2 years', '+1 year'),
            'content' => fake()->paragraphs(3, true),
            'created_by_user_id' => User::factory()->state(['type' => 'admin']),
        ];
    }
}
