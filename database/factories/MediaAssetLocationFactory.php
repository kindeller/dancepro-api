<?php

namespace Database\Factories;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaAssetLocation;
use App\Features\Media\Support\MediaAssetLocationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAssetLocation> */
class MediaAssetLocationFactory extends Factory
{
    protected $model = MediaAssetLocation::class;

    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'storage_disk' => 'local',
            'storage_key' => 'local-development/archive/'.fake()->uuid().'.mp4',
            'status' => MediaAssetLocationStatus::Active,
            'became_active_at' => now(),
            'metadata' => ['environment' => 'local'],
        ];
    }
}
