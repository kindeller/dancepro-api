<?php

namespace Database\Factories;

use App\Features\Crew\Models\CrewRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CrewRole> */
class CrewRoleFactory extends Factory
{
    protected $model = CrewRole::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => Str::slug($name, '_'),
            'name' => $name,
            'event_type' => fake()->randomElement(['competition', 'concert', null]),
            'is_active' => true,
        ];
    }
}
