<?php

namespace Database\Factories;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewProfile> */
class CrewProfileFactory extends Factory
{
    protected $model = CrewProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'legal_name' => fake()->name(),
            'preferred_name' => fake()->firstName(),
            'phone' => fake()->phoneNumber(),
            'shirt_size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'jacket_size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'commencement_date' => fake()->dateTimeBetween('-10 years', '-1 month'),
        ];
    }
}
