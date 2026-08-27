<?php

namespace Database\Factories;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaCollection> */
class MediaCollectionFactory extends Factory
{
    protected $model = MediaCollection::class;

    public function definition(): array
    {
        return [
            'concert_id' => Concert::factory(),
            'competition_id' => null,
            'name' => fake()->words(2, true),
            'media_type' => MediaType::Photo,
            'catalogue_mode' => MediaCatalogueMode::Storage,
            'status' => MediaCollectionStatus::Draft,
            'visibility' => MediaCollectionVisibility::Private,
            'storage_disk' => 's3_concerts',
            'storage_prefix' => fake()->uuid().'/',
        ];
    }

    public function forCompetition(int $competitionId): static
    {
        return $this->state(fn () => ['concert_id' => null, 'competition_id' => $competitionId]);
    }
}
