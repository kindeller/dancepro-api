<?php

namespace Database\Factories;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DownloadLink>
 */
class DownloadLinkFactory extends Factory
{
    protected $model = DownloadLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'uuid' => (string) Str::uuid(),
            'generated_by_user_id' => User::factory(),
            'storage_disk' => 's3',
            'storage_key' => 'folder/file.mp4',
            'original_filename' => 'file.mp4',
            'purpose' => 'Feature test',
            'token_hash' => hash('sha256', $token),
            'status' => DownloadLinkStatus::ACTIVE,
            'expires_at' => now()->addDays(60),
            'download_count' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DownloadLinkStatus::EXPIRED,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DownloadLinkStatus::REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
