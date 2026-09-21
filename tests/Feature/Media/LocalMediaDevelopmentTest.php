<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCollectionStatus;
use App\Features\Media\Support\MediaType;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalMediaDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('filesystems.disks.s3_concerts.driver', 'local');
        Storage::fake('s3_concerts');
    }

    public function test_local_playback_and_branding_do_not_require_or_redirect_to_cloudfront(): void
    {
        $concert = Concert::factory()->published()->create();
        $collection = MediaCollection::factory()->for($concert)->create([
            'status' => MediaCollectionStatus::Published,
            'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
        ]);
        $key = 'concerts/demo.mp4';
        Storage::disk('s3_concerts')->put($key, 'local-video');
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 's3_concerts',
            'storage_key' => $key,
            'media_type' => MediaType::Video,
            'status' => MediaAssetStatus::Available,
            'is_visible' => true,
        ]);
        config()->set('concerts.playback.cloudfront.domain', 'live.example.test');

        $playback = $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertOk()->assertJsonPath('data.format', 'progressive');
        $this->assertStringContainsString('/media/'.$asset->uuid, $playback->json('data.url'));
        $this->assertStringNotContainsString('live.example.test', $playback->json('data.url'));
        $this->assertStringContainsString('no-store', $this->get($playback->json('data.url'))->assertOk()->headers->get('Cache-Control'));
        $this->get(route('concerts.media.download', [$concert, $asset]))->assertOk();

        $coverKey = "concerts/{$concert->uuid}/cover";
        Storage::disk('s3_concerts')->put($coverKey, 'local-cover');
        $concert->update(['cover_image_storage_key' => $coverKey]);
        $this->assertStringContainsString('no-store', $this->get(route('concerts.cover', $concert))->assertOk()->headers->get('Cache-Control'));
        $this->withHeader('Range', 'bytes=0-4')->get($playback->json('data.url'))->assertStatus(206);
    }

    public function test_signed_local_multipart_upload_can_complete_and_rejects_tampering(): void
    {
        $concert = Concert::factory()->create();
        $collection = MediaCollection::factory()->for($concert)->create(['storage_disk' => 's3_concerts']);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create(['storage_disk' => 's3_concerts']);
        $body = '123456789';
        $checksum = base64_encode(hex2bin('AE8B14860A799888'));
        $key = $collection->uuid.'/media/'.$asset->uuid.'/original/video.mp4';
        $storage = app(S3MediaStorage::class);
        $uploadId = $storage->startMultipart('s3_concerts', $key, 'video/mp4')['upload_id'];
        MediaUpload::query()->create([
            'media_asset_id' => $asset->id,
            'user_id' => User::factory()->create()->id,
            'kind' => MediaUploadKind::Multipart,
            'status' => MediaUploadStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'relative_path' => 'original/video.mp4',
            'storage_disk' => 's3_concerts',
            'storage_key' => $key,
            'provider_upload_id' => $uploadId,
            'content_type' => 'video/mp4',
            'size_bytes' => strlen($body),
            'checksum_algorithm' => 'CRC64NVME',
            'checksum' => $checksum,
            'expires_at' => now()->addHour(),
        ]);
        $url = $storage->presignPart('s3_concerts', $key, $uploadId, 1, $checksum, now()->addMinute())['url'];
        $this->call('PUT', $url.'&key=tampered', [], [], [], [], $body)->assertForbidden();
        $response = $this->call('PUT', $url, [], [], [], [], $body)->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $storage->completeMultipart('s3_concerts', $key, $uploadId, [[
            'PartNumber' => 1, 'ETag' => $etag, 'ChecksumCRC64NVME' => $checksum,
        ]], $checksum, strlen($body));
        Storage::disk('s3_concerts')->assertExists($key);
        $this->assertSame($checksum, $storage->head('s3_concerts', $key)['checksum_crc64nvme']);
    }

    public function test_existing_local_demo_media_still_plays(): void
    {
        Storage::fake('local');
        $concert = Concert::factory()->published()->create();
        $collection = MediaCollection::factory()->for($concert)->create([
            'status' => MediaCollectionStatus::Published,
            'media_type' => MediaType::Video,
            'storage_disk' => 'local',
        ]);
        $key = 'demo/video.mp4';
        Storage::disk('local')->put($key, 'demo-video');
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 'local',
            'storage_key' => $key,
            'media_type' => MediaType::Video,
            'status' => MediaAssetStatus::Available,
            'is_visible' => true,
        ]);

        $url = $this->getJson(route('concerts.media.playback', [$concert, $asset]))
            ->assertOk()->json('data.url');
        $this->get($url)->assertOk();
    }

    public function test_signed_local_single_object_upload_verifies_checksum_and_size(): void
    {
        $concert = Concert::factory()->create();
        $collection = MediaCollection::factory()->for($concert)->create(['storage_disk' => 's3_concerts']);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create(['storage_disk' => 's3_concerts']);
        $body = 'local-segment';
        $key = $collection->uuid.'/media/'.$asset->uuid.'/stream/720p-000001.m4s';
        $checksum = base64_encode(hash('sha256', $body, true));
        MediaUpload::query()->create([
            'media_asset_id' => $asset->id,
            'user_id' => User::factory()->create()->id,
            'kind' => MediaUploadKind::SingleBatch,
            'status' => MediaUploadStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'storage_disk' => 's3_concerts',
            'files' => [[
                'storage_key' => $key,
                'relative_path' => 'stream/720p-000001.m4s',
                'content_type' => 'video/iso.segment',
                'size_bytes' => strlen($body),
                'checksum_sha256' => $checksum,
            ]],
            'expires_at' => now()->addHour(),
        ]);
        $url = app(S3MediaStorage::class)->presignPut('s3_concerts', $key, 'video/iso.segment', $checksum, now()->addMinute())['url'];

        $this->call('PUT', $url, [], [], [], [], 'wrong-segment')->assertUnprocessable();
        Storage::disk('s3_concerts')->assertMissing($key);
        $this->call('PUT', $url, [], [], [], [], $body)->assertOk();
        $this->assertSame($checksum, app(S3MediaStorage::class)->head('s3_concerts', $key)['checksum_sha256']);
    }

    public function test_external_media_links_are_hidden_locally_without_changing_saved_records(): void
    {
        $concert = Concert::factory()->published()->create([
            'cover_image_url' => 'https://live-media.example.test/cover.jpg',
            'program_url' => 'https://live-media.example.test/program.pdf',
            'external_gallery_url' => 'https://live-media.example.test/gallery',
        ]);
        $concert->studio->update(['cover_image_url' => 'https://live-media.example.test/studio.jpg']);

        $this->get(route('concerts.show', $concert))->assertOk()->assertDontSee('live-media.example.test');
        $this->get(route('studios.show', $concert->studio))->assertOk()->assertDontSee('live-media.example.test');
        $this->getJson('/api/concerts/'.$concert->uuid)
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', null)
            ->assertJsonPath('data.program_available', false)
            ->assertJsonPath('data.gallery_available', false);
        $this->assertSame('https://live-media.example.test/program.pdf', $concert->refresh()->program_url);
    }
}
