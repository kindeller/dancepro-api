<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Studios\Models\Studio;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConcertDomainModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_and_concert_have_public_uuids_and_relationships(): void
    {
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->for($studio)->create();

        $this->assertNotNull($studio->uuid);
        $this->assertNotNull($concert->uuid);
        $this->assertTrue($studio->concerts->first()->is($concert));
        $this->assertTrue($concert->studio->is($studio));
        $this->assertSame('uuid', $concert->getRouteKeyName());

        $concert->delete();
        $this->assertSoftDeleted($concert);
        $this->assertDatabaseHas('studios', ['id' => $studio->id, 'deleted_at' => null]);
    }

    public function test_concert_password_is_hashed_and_can_be_checked(): void
    {
        $concert = Concert::factory()->create(['access_password_hash' => 'secret']);

        $this->assertNotSame('secret', $concert->access_password_hash);
        $this->assertTrue(Hash::check('secret', $concert->access_password_hash));
        $this->assertTrue($concert->passwordMatches('secret'));
        $this->assertFalse($concert->passwordMatches('wrong'));
    }

    public function test_media_collection_requires_exactly_one_owner(): void
    {
        $this->expectException(QueryException::class);

        MediaCollection::factory()->create(['concert_id' => null, 'competition_id' => null]);
    }

    public function test_assets_use_full_storage_identity_not_basename(): void
    {
        $collection = MediaCollection::factory()->create();
        $first = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_key' => 'concert-a/photos/IMG_0001.jpg',
            'original_filename' => 'IMG_0001.jpg',
        ]);
        $second = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_key' => 'concert-b/photos/IMG_0001.jpg',
            'original_filename' => 'IMG_0001.jpg',
        ]);

        $this->assertNotSame($first->uuid, $second->uuid);
        $this->assertSame('IMG_0001.jpg', $first->original_filename);
        $this->assertSame('IMG_0001.jpg', $second->original_filename);

        $this->expectException(QueryException::class);
        MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => $first->storage_disk,
            'storage_key' => $first->storage_key,
        ]);
    }
}
