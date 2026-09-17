<?php

namespace Database\Factories;

use App\Features\Customers\Models\CustomerProfile;
use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerProfile> */
class CustomerProfileFactory extends Factory
{
    protected $model = CustomerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::Customer->value]),
            'preferred_name' => fake()->firstName(),
            'registration_source' => 'local_development',
            'terms_accepted_at' => now()->subDays(30),
            'privacy_accepted_at' => now()->subDays(30),
            'preferences' => ['email_updates' => false],
        ];
    }
}
