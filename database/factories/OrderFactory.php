<?php

namespace Database\Factories;

use App\Features\Customers\Support\UserType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::Customer->value]),
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'status' => OrderStatus::Draft,
            'currency' => 'AUD',
            'subtotal_amount' => 1500,
            'total_amount' => 1500,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'placed_at' => now()->subDays(2),
            'paid_at' => now()->subDays(2),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->paid()->state(fn () => [
            'status' => OrderStatus::Fulfilled,
            'fulfilled_at' => now()->subDay(),
        ]);
    }
}
