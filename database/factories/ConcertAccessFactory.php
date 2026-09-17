<?php

namespace Database\Factories;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Support\ConcertAccessMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConcertAccess> */
class ConcertAccessFactory extends Factory
{
    protected $model = ConcertAccess::class;

    public function definition(): array
    {
        return [
            'concert_id' => Concert::factory(),
            'access_method' => ConcertAccessMethod::Password,
            'accessed_at' => now(),
            'session_identifier' => fake()->uuid(),
            'student_name' => fake()->name(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'DancePro local development browser',
            'was_successful' => true,
        ];
    }

    public function failed(string $reason = 'invalid_password'): static
    {
        return $this->state(fn () => ['was_successful' => false, 'failure_reason' => $reason]);
    }
}
