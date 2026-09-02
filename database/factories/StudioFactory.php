<?php

namespace Database\Factories;

use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Studio> */
class StudioFactory extends Factory
{
    protected $model = Studio::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('STU-###'),
            'slug' => fake()->unique()->slug(3),
            'status' => StudioStatus::Active,
            'description' => fake()->sentence(12),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->companyEmail(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StudioStatus::Inactive]);
    }
}
