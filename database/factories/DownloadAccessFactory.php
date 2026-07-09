<?php

namespace Database\Factories;

use App\Features\Downloads\Models\DownloadAccess;
use App\Features\Downloads\Models\DownloadLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadAccess>
 */
class DownloadAccessFactory extends Factory
{
    protected $model = DownloadAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'download_link_id' => DownloadLink::factory(),
            'accessed_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'referrer' => null,
            'was_successful' => true,
        ];
    }
}
