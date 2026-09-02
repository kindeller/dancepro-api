<?php

namespace Database\Factories;

use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'type' => 'staff',
            'is_active' => true,
            'is_admin' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be unable to authenticate.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['type' => UserType::Staff->value, 'is_admin' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['type' => UserType::Admin->value, 'is_admin' => true]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['type' => UserType::Customer->value, 'is_admin' => false]);
    }

    public function crew(): static
    {
        return $this->state(fn () => ['type' => UserType::Crew->value, 'is_admin' => false, 'onboarding_completed_at' => now()]);
    }
}
