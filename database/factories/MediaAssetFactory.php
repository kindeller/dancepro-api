<?php

namespace Database\Factories;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $filename = 'IMG_'.fake()->unique()->numberBetween(1000, 999999).'.jpg';

        return [
            'media_collection_id' => MediaCollection::factory(),
            'media_type' => MediaType::Photo,
            'storage_disk' => 's3_concerts',
            'storage_key' => 'photos/'.fake()->uuid().'/'.$filename,
            'original_filename' => $filename,
            'status' => MediaAssetStatus::Available,
            'is_visible' => true,
            'extension' => 'jpg',
        ];
    }
}
