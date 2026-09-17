<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminConcertMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_staff_can_open_the_media_page_and_reserve_media_from_a_web_session(): void
    {
        $concert = Concert::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.concerts.media.index', $concert))->assertForbidden();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.media-api.collections.store', $concert), [
                'name' => 'Not allowed', 'media_type' => 'video',
            ])->assertForbidden();

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->get(route('admin.concerts.media.index', $concert))
            ->assertOk()
            ->assertSee('Add the first video')
            ->assertSee('Original MP4')
            ->assertSee('Playback MP4');

        $collection = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.media-api.collections.store', $concert), [
                'name' => 'Performances', 'media_type' => 'video',
            ])->assertCreated();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.media-api.assets.store', $collection->json('data.uuid')), [
                'media_type' => 'video',
                'display_name' => 'Opening',
                'original_filename' => 'opening.mp4',
                'expected_outputs' => ['original', 'fallback_mp4'],
            ])->assertCreated()->assertJsonPath('data.status', 'processing');
    }

    public function test_collection_cannot_publish_until_a_verified_asset_is_visible(): void
    {
        $staff = User::factory()->staff()->create();
        $collection = MediaCollection::factory()->for(Concert::factory())->create([
            'status' => MediaCollectionStatus::Draft,
            'catalogue_mode' => MediaCatalogueMode::Managed,
            'media_type' => MediaType::Video,
        ]);
        $url = route('admin.media-api.collections.update', $collection);

        $this->actingAs($staff)->patchJson($url, ['status' => 'published'])
            ->assertUnprocessable();

        MediaAsset::factory()->for($collection, 'collection')->create([
            'media_type' => MediaType::Video,
            'status' => MediaAssetStatus::Available,
            'verified_at' => now(),
            'is_visible' => true,
        ]);

        $this->actingAs($staff)->patchJson($url, ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertSame('public', $collection->refresh()->visibility->value);
        $this->assertNotNull($collection->published_at);
    }

    public function test_media_management_lists_collections_and_requires_exact_delete_confirmation(): void
    {
        Storage::fake('s3_concerts');
        $concert = Concert::factory()->create();
        $collection = MediaCollection::factory()->for($concert)->create([
            'catalogue_mode' => MediaCatalogueMode::Managed,
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
            'display_name' => 'Ballet',
        ]);
        $prefix = "{$collection->uuid}/media/{$asset->uuid}/";
        Storage::disk('s3_concerts')->put($prefix.'original/video.mp4', 'original');
        Storage::disk('s3_concerts')->put($prefix.'stream/fallback.mp4', 'playback');

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.concerts.edit', $concert))
            ->assertOk()->assertSee('Collections and videos')->assertSee('Ballet')->assertSee('Upload media');

        $preview = $this->get(route('admin.concerts.media.assets.confirm-delete', $asset))
            ->assertOk()->assertSee($prefix.'original/video.mp4')->assertSee($prefix.'stream/fallback.mp4');
        $digest = $preview->viewData('digest');

        $this->delete(route('admin.concerts.media.assets.destroy', $asset), [
            'confirmation' => 'DELETE', 'digest' => str_repeat('0', 64),
        ])->assertSessionHasErrors('confirmation');
        Storage::disk('s3_concerts')->assertExists($prefix.'original/video.mp4');

        $this->delete(route('admin.concerts.media.assets.destroy', $asset), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertRedirect(route('admin.concerts.edit', $concert));
        Storage::disk('s3_concerts')->assertMissing($prefix.'original/video.mp4');
        Storage::disk('s3_concerts')->assertMissing($prefix.'stream/fallback.mp4');
        $this->assertSoftDeleted($asset);
    }

    public function test_legacy_collection_cannot_be_deleted_from_managed_media_flow(): void
    {
        $collection = MediaCollection::factory()->for(Concert::factory())->create([
            'catalogue_mode' => MediaCatalogueMode::Storage,
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts_legacy',
        ]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.concerts.media.collections.confirm-delete', $collection))
            ->assertUnprocessable();
    }

    public function test_collection_delete_rechecks_object_list_before_removing_database_rows(): void
    {
        Storage::fake('s3_concerts');
        $collection = MediaCollection::factory()->for(Concert::factory())->create([
            'catalogue_mode' => MediaCatalogueMode::Managed,
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
        ]);
        $prefix = "{$collection->uuid}/media/{$asset->uuid}/";
        Storage::disk('s3_concerts')->put($prefix.'original/video.mp4', 'original');
        $this->actingAs(User::factory()->staff()->create());
        $digest = $this->get(route('admin.concerts.media.collections.confirm-delete', $collection))
            ->assertOk()->viewData('digest');

        Storage::disk('s3_concerts')->put($prefix.'stream/fallback.mp4', 'playback');
        $this->delete(route('admin.concerts.media.collections.destroy', $collection), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($collection);
        Storage::disk('s3_concerts')->assertExists($prefix.'original/video.mp4');

        $digest = $this->get(route('admin.concerts.media.collections.confirm-delete', $collection))
            ->assertOk()->viewData('digest');
        $this->delete(route('admin.concerts.media.collections.destroy', $collection), [
            'confirmation' => 'DELETE', 'digest' => $digest,
        ])->assertRedirect();
        $this->assertSoftDeleted($collection);
        $this->assertSoftDeleted($asset);
        Storage::disk('s3_concerts')->assertMissing($prefix.'original/video.mp4');
        Storage::disk('s3_concerts')->assertMissing($prefix.'stream/fallback.mp4');
    }
}
