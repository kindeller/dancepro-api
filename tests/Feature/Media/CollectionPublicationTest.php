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
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class CollectionPublicationTest extends TestCase
{
    use AuthenticatesApiTokens;
    use RefreshDatabase;

    public function test_confirmed_publication_shows_only_ready_videos_and_preserves_concert_approval(): void
    {
        $collection = $this->collection();
        $ready = $this->asset($collection);
        $processing = $this->asset($collection, ['status' => MediaAssetStatus::Processing, 'verified_at' => null]);
        $unverified = $this->asset($collection, ['verified_at' => null]);
        $missing = $this->asset($collection, ['status' => MediaAssetStatus::Missing]);
        $otherCollection = $this->asset($this->collection());
        $deleted = $this->asset($collection);
        $deleted->delete();

        $this->actingAs(User::factory()->staff()->create())
            ->patchJson(route('admin.media-api.collections.update', $collection), [
                'status' => 'published', 'show_all_verified' => true,
            ])->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.visibility', 'public');

        $this->assertTrue($ready->fresh()->is_visible);
        foreach ([$processing, $unverified, $missing, $otherCollection] as $asset) {
            $this->assertFalse($asset->fresh()->is_visible);
        }
        $this->assertFalse(MediaAsset::withTrashed()->find($deleted->id)->is_visible);
        $this->assertSame('draft', $collection->concert->fresh()->status->value);
        $this->patchJson(route('admin.media-api.assets.update', $ready), ['is_visible' => false])->assertOk();
        $this->assertFalse($ready->fresh()->is_visible);
    }

    public function test_existing_api_publish_requests_preserve_hidden_videos_and_can_opt_in(): void
    {
        $collection = $this->collection();
        $visible = $this->asset($collection, ['is_visible' => true]);
        $hidden = $this->asset($collection);
        $this->actingAsApiUser(User::factory()->staff()->create(), ['concert-media:update']);
        $url = '/api/staff/media-collections/'.$collection->uuid;

        $this->patchJson($url, ['status' => 'published'])->assertOk();
        $this->assertTrue($visible->fresh()->is_visible);
        $this->assertFalse($hidden->fresh()->is_visible);
        $this->patchJson($url, ['status' => 'published', 'show_all_verified' => true])->assertOk();
        $this->assertTrue($hidden->fresh()->is_visible);
    }

    public function test_show_all_cannot_publish_unverified_media_or_change_visibility_while_drafting(): void
    {
        $collection = $this->collection();
        $processing = $this->asset($collection, ['status' => MediaAssetStatus::Processing, 'verified_at' => null]);
        $this->actingAs(User::factory()->staff()->create());
        $url = route('admin.media-api.collections.update', $collection);

        $this->patchJson($url, ['status' => 'published', 'show_all_verified' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(MediaCollectionStatus::Draft, $collection->fresh()->status);
        $this->assertFalse($processing->fresh()->is_visible);

        $ready = $this->asset($collection);
        $this->patchJson($url, ['status' => 'draft', 'show_all_verified' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('show_all_verified');
        $this->patchJson($url, ['show_all_verified' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('show_all_verified');
        $this->assertFalse($ready->fresh()->is_visible);
    }

    public function test_both_admin_pages_warn_before_showing_hidden_videos_and_offer_processing_deletion(): void
    {
        $collection = $this->collection();
        $this->asset($collection);
        $processing = $this->asset($collection, ['status' => MediaAssetStatus::Processing, 'verified_at' => null]);
        $this->actingAs(User::factory()->staff()->create());

        foreach (['admin.concerts.edit', 'admin.concerts.media.index'] as $route) {
            $this->get(route($route, $collection->concert))->assertOk()
                ->assertSee('data-publish-warning', false)->assertSee('data-show-all-verified="1"', false)
                ->assertSee('1 verified video currently hidden.')->assertSee('1 video not ready')
                ->assertSee(route('admin.concerts.media.assets.confirm-delete', $processing), false);
        }
    }

    public function test_show_all_respects_existing_authorization_and_legacy_storage_boundaries(): void
    {
        $collection = $this->collection();
        $ready = $this->asset($collection);
        $url = '/api/staff/media-collections/'.$collection->uuid;
        $payload = ['status' => 'published', 'show_all_verified' => true];
        $this->actingAsApiUser(User::factory()->staff()->create(), ['concert-media:read']);
        $this->patchJson($url, $payload)->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['concert-media:update']);
        $this->patchJson($url, $payload)->assertForbidden();
        $collection->update(['storage_disk' => 's3_concerts_legacy', 'catalogue_mode' => MediaCatalogueMode::Storage]);
        $this->actingAsApiUser(User::factory()->staff()->create(), ['concert-media:update']);
        $this->patchJson($url, $payload)->assertUnprocessable();
        $this->assertFalse($ready->fresh()->is_visible);
    }

    private function collection(): MediaCollection
    {
        return MediaCollection::factory()->for(Concert::factory())->create([
            'status' => MediaCollectionStatus::Draft, 'catalogue_mode' => MediaCatalogueMode::Managed,
            'media_type' => MediaType::Video, 'storage_disk' => 's3_concerts',
        ]);
    }

    private function asset(MediaCollection $collection, array $attributes = []): MediaAsset
    {
        return MediaAsset::factory()->for($collection, 'collection')->create(array_merge([
            'media_type' => MediaType::Video, 'status' => MediaAssetStatus::Available,
            'is_visible' => false, 'verified_at' => now(), 'storage_disk' => 's3_concerts',
        ], $attributes));
    }
}
