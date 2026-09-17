<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaCollectionVisibility;
use App\Features\Media\Support\MediaType;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class MediaIngestApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use RefreshDatabase;

    public function test_staff_discovery_includes_draft_concerts_but_requires_read_ability(): void
    {
        $concert = Concert::factory()->create(['name' => 'Unreleased Concert']);
        $user = User::factory()->staff()->create();

        $this->actingAsApiUser($user, ['concert-media:read']);

        $this->getJson('/api/staff/concerts')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $concert->uuid)
            ->assertJsonPath('data.0.name', 'Unreleased Concert')
            ->assertJsonPath('data.0.status', 'draft');

        $this->actingAsApiUser($user, ['concert-media:upload']);
        $this->getJson('/api/staff/concerts')->assertForbidden();
    }

    public function test_collection_and_asset_reservation_are_idempotent_and_server_assigns_storage(): void
    {
        $concert = Concert::factory()->create();
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:upload']);

        $collectionKey = (string) Str::uuid();
        $payload = ['name' => 'Evening', 'media_type' => 'video', 'sort_order' => 10];
        $first = $this->withHeader('Idempotency-Key', $collectionKey)
            ->postJson("/api/staff/concerts/{$concert->uuid}/media-collections", $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.visibility', 'private');
        $this->withHeader('Idempotency-Key', $collectionKey)
            ->postJson("/api/staff/concerts/{$concert->uuid}/media-collections", $payload)
            ->assertCreated()
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $assetKey = (string) Str::uuid();
        $assetPayload = [
            'media_type' => 'video',
            'display_name' => 'Opening Performance',
            'original_filename' => 'opening.mov',
            'expected_outputs' => ['original', 'fallback_mp4'],
        ];
        $asset = $this->withHeader('Idempotency-Key', $assetKey)
            ->postJson('/api/staff/media-collections/'.$first->json('data.uuid').'/media-assets', $assetPayload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.is_visible', false);

        $record = MediaAsset::query()->where('uuid', $asset->json('data.uuid'))->firstOrFail();
        $this->assertSame('s3_concerts', $record->storage_disk);
        $this->assertSame($record->collection->uuid.'/media/'.$record->uuid.'/original/video.mp4', $record->storage_key);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_upload_url_batch_uses_exact_server_derived_key_and_rejects_unsafe_paths(): void
    {
        $asset = $this->processingAsset();
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:upload']);
        $checksum = base64_encode(str_repeat('a', 32));

        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($asset, $checksum): void {
            $mock->shouldReceive('presignPut')
                ->once()
                ->withArgs(fn (string $disk, string $key, string $type, string $givenChecksum): bool => $disk === 's3_concerts'
                    && $key === $asset->collection->uuid.'/media/'.$asset->uuid.'/stream/720p-000001.m4s'
                    && $type === 'video/iso.segment'
                    && $givenChecksum === $checksum)
                ->andReturn(['url' => 'https://upload.example.test/signed', 'headers' => ['x-amz-checksum-sha256' => $checksum]]);
        });

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-assets/{$asset->uuid}/upload-urls", ['files' => [[
                'relative_path' => 'stream/720p-000001.m4s',
                'content_type' => 'video/iso.segment',
                'size_bytes' => 1024,
                'checksum_sha256' => $checksum,
            ]]])
            ->assertCreated()
            ->assertJsonPath('data.objects.0.method', 'PUT')
            ->assertJsonPath('data.objects.0.url', 'https://upload.example.test/signed');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-assets/{$asset->uuid}/upload-urls", ['files' => [[
                'relative_path' => '../escape.mp4',
                'content_type' => 'video/mp4',
                'size_bytes' => 1024,
                'checksum_sha256' => $checksum,
            ]]])
            ->assertUnprocessable();
    }

    public function test_fallback_only_finalization_requires_both_download_original_and_compressed_playback_mp4(): void
    {
        $asset = $this->processingAsset();
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:upload']);

        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('head')->twice()->andReturn([
                'size' => 2048,
                'content_type' => 'video/mp4',
                'checksum_sha256' => null,
                'checksum_crc64nvme' => null,
            ]);
        });

        $this->postJson("/api/staff/media-assets/{$asset->uuid}/finalize", [
            'expected_outputs' => ['original', 'fallback_mp4'],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.is_visible', false);

        $asset->refresh();
        $this->assertSame(MediaAssetStatus::Available, $asset->status);
        $this->assertNotNull($asset->verified_at);
    }

    public function test_multipart_upload_is_coordinated_without_returning_aws_credentials(): void
    {
        $asset = $this->processingAsset();
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:upload']);
        $checksum = base64_encode(str_repeat('c', 8));

        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($checksum): void {
            $mock->shouldReceive('startMultipart')->once()->withArgs(fn ($disk, $key, $contentType, $sourceFilename) =>
                $disk === 's3_concerts' && $contentType === 'video/mp4' && $sourceFilename === 'evening-show.mp4'
                    && str_ends_with($key, '/stream/fallback.mp4'))
                ->andReturn(['upload_id' => 'private-provider-id']);
            $mock->shouldReceive('presignPart')->once()->andReturn([
                'url' => 'https://upload.example.test/part-1',
                'headers' => ['x-amz-checksum-crc64nvme' => $checksum],
            ]);
            $mock->shouldReceive('completeMultipart')->once();
            $mock->shouldReceive('head')->twice()->andReturn(null, [
                'size' => 5242880,
                'content_type' => 'video/mp4',
                'checksum_sha256' => null,
                'checksum_crc64nvme' => $checksum,
            ]);
        });

        $started = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-assets/{$asset->uuid}/multipart-uploads", [
                'relative_path' => 'stream/fallback.mp4',
                'content_type' => 'video/mp4',
                'size_bytes' => 5242880,
                'checksum_algorithm' => 'CRC64NVME',
                'checksum' => $checksum,
                'source_filename' => 'evening-show.mp4',
            ])
            ->assertCreated()
            ->assertJsonMissing(['provider_upload_id'])
            ->assertJsonMissing(['credentials']);

        $uploadUuid = $started->json('data.upload_uuid');
        $this->postJson("/api/staff/media-assets/{$asset->uuid}/multipart-uploads/{$uploadUuid}/parts", [
            'parts' => [['part_number' => 1, 'checksum_crc64nvme' => $checksum]],
        ])->assertOk()->assertJsonPath('data.parts.0.url', 'https://upload.example.test/part-1');

        $this->postJson("/api/staff/media-assets/{$asset->uuid}/multipart-uploads/{$uploadUuid}/complete", [
            'parts' => [['part_number' => 1, 'etag' => '"etag-value"', 'checksum_crc64nvme' => $checksum]],
            'checksum_crc64nvme' => $checksum,
        ])->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_hls_master_upload_is_refused_until_child_playlist_and_segment_are_verified(): void
    {
        $asset = $this->processingAsset();
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:upload']);
        $checksum = base64_encode(str_repeat('m', 32));

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-assets/{$asset->uuid}/upload-urls", ['files' => [[
                'relative_path' => 'stream/master.m3u8',
                'content_type' => 'application/vnd.apple.mpegurl',
                'size_bytes' => 512,
                'checksum_sha256' => $checksum,
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files');
    }

    public function test_legacy_listing_returns_opaque_reference_and_imports_hidden_mp4(): void
    {
        config(['media.legacy_disk' => 'legacy_test']);
        Storage::fake('legacy_test');
        Storage::disk('legacy_test')->put('concert-42/opening.mp4', 'legacy-video');

        $studio = Studio::factory()->create();
        $concert = Concert::factory()->for($studio)->create([
            'storage_disk' => 'legacy_test',
            'storage_prefix' => 'concert-42',
        ]);
        $collection = MediaCollection::factory()->for($concert)->create([
            'media_type' => MediaType::Video,
            'catalogue_mode' => MediaCatalogueMode::Managed,
            'storage_disk' => 's3_concerts',
        ]);
        $user = User::factory()->staff()->create();

        $this->actingAsApiUser($user, ['concert-media:read']);
        $listing = $this->getJson("/api/staff/concerts/{$concert->uuid}/legacy-media")
            ->assertOk()
            ->assertJsonPath('data.objects.0.filename', 'opening.mp4');
        $reference = $listing->json('data.objects.0.object_ref');
        $this->assertIsString($reference);
        $this->assertStringNotContainsString('concert-42/opening.mp4', $reference);

        $this->actingAsApiUser($user, ['concert-media:upload']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-collections/{$collection->uuid}/imports", [
                'object_ref' => $reference,
                'display_name' => 'Opening',
                'is_visible' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.is_visible', false);

        $this->assertDatabaseHas('media_assets', [
            'media_collection_id' => $collection->id,
            'storage_disk' => 'legacy_test',
            'storage_key' => 'concert-42/opening.mp4',
            'is_visible' => false,
        ]);
    }

    private function processingAsset(): MediaAsset
    {
        $collection = MediaCollection::factory()->create([
            'media_type' => MediaType::Video,
            'catalogue_mode' => MediaCatalogueMode::Managed,
            'status' => MediaCollectionStatus::Draft,
            'visibility' => MediaCollectionVisibility::Private,
            'storage_disk' => 's3_concerts',
        ]);

        return MediaAsset::factory()->for($collection, 'collection')->create([
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
            'status' => MediaAssetStatus::Processing,
            'is_visible' => false,
            'verified_at' => null,
            'metadata' => ['ingest' => ['expected_outputs' => ['original', 'fallback_mp4']]],
        ])->load('collection');
    }
}
