<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
use App\Features\Media\Actions\RecordMediaIngestEvent;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Media\Support\MediaAssetStatus;
use App\Features\Media\Support\MediaCatalogueMode;
use App\Features\Media\Support\MediaType;
use App\Features\Media\Support\MediaUploadKind;
use App\Features\Media\Support\MediaUploadStatus;
use App\Models\User;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class MediaIngestRecoveryTest extends TestCase
{
    use AuthenticatesApiTokens;
    use RefreshDatabase;

    public function test_retry_recovers_after_s3_completion_and_database_transaction_failure(): void
    {
        $upload = $this->upload();
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($upload): void {
            $mock->shouldReceive('head')->times(3)->andReturn(null, $this->objectMetadata($upload), $this->objectMetadata($upload));
            $mock->shouldReceive('completeMultipart')->once();
        });
        $calls = 0;
        $recorder = new RecordMediaIngestEvent;
        $this->mock(RecordMediaIngestEvent::class, function (MockInterface $mock) use (&$calls, $recorder): void {
            $mock->shouldReceive('handle')->twice()->andReturnUsing(function (...$arguments) use (&$calls, $recorder) {
                if (++$calls === 1) {
                    throw new RuntimeException('Simulated database write failure');
                }

                return $recorder->handle(...$arguments);
            });
        });

        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertStatus(500);
        $this->assertSame(MediaUploadStatus::Pending, $upload->fresh()->status);
        $this->assertDatabaseCount('media_ingest_events', 0);

        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertOk();
        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertOk();
        $this->assertSame(MediaUploadStatus::Completed, $upload->fresh()->status);
        $this->assertDatabaseCount('media_ingest_events', 1);
    }

    public function test_lost_s3_completion_response_is_reconciled(): void
    {
        $upload = $this->upload();
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($upload): void {
            $mock->shouldReceive('head')->twice()->andReturn(null, $this->objectMetadata($upload));
            $mock->shouldReceive('completeMultipart')->once()->andThrow(new AwsException('Response lost', new Command('CompleteMultipartUpload')));
        });

        $this->postJson($this->completeUrl($upload), $this->completion($upload))
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }

    #[DataProvider('invalidObjects')]
    public function test_recovery_never_accepts_unverified_existing_objects(?string $checksum, int $size): void
    {
        $upload = $this->upload();
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($checksum, $size): void {
            $mock->shouldReceive('head')->once()->andReturn(['size' => $size, 'checksum_crc64nvme' => $checksum]);
            $mock->shouldNotReceive('completeMultipart');
        });

        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertUnprocessable();
        $this->assertSame(MediaUploadStatus::Pending, $upload->fresh()->status);
        $this->assertDatabaseCount('media_ingest_events', 0);
    }

    public static function invalidObjects(): array
    {
        return [
            'missing checksum' => [null, 5242880],
            'wrong checksum' => [base64_encode('xxxxxxxx'), 5242880],
            'wrong size' => [base64_encode('cccccccc'), 1],
        ];
    }

    public function test_legacy_collection_cannot_reserve_upload_assets(): void
    {
        $upload = $this->upload();
        $collection = $upload->asset->collection;
        $collection->update(['storage_disk' => 's3_concerts_legacy']);
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldNotReceive('startMultipart', 'presignPut'));

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/staff/media-collections/{$collection->uuid}/media-assets", [
                'media_type' => 'video', 'display_name' => 'Video', 'original_filename' => 'video.mp4',
                'expected_outputs' => ['original', 'fallback_mp4'],
            ])->assertUnprocessable()->assertJsonValidationErrors('storage_disk');
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_existing_legacy_asset_cannot_sign_complete_or_abort_uploads(): void
    {
        $upload = $this->upload();
        $upload->asset->update(['storage_disk' => 's3_concerts_legacy']);
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldNotReceive('head', 'presignPut', 'startMultipart', 'presignPart', 'completeMultipart', 'abortMultipart'));
        $base = "/api/staff/media-assets/{$upload->asset->uuid}";

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/upload-urls', ['files' => [[
            'relative_path' => 'stream/fallback.mp4', 'content_type' => 'video/mp4', 'size_bytes' => 1,
            'checksum_sha256' => base64_encode(str_repeat('s', 32)),
        ]]])->assertUnprocessable();
        $this->postJson($base.'/multipart-uploads', [
            'relative_path' => 'stream/fallback.mp4', 'content_type' => 'video/mp4', 'size_bytes' => 5242880,
            'checksum_algorithm' => 'CRC64NVME', 'checksum' => $upload->checksum,
        ])->assertUnprocessable();
        $this->postJson($base."/multipart-uploads/{$upload->uuid}/parts", ['parts' => [[
            'part_number' => 1, 'checksum_crc64nvme' => $upload->checksum,
        ]]])->assertUnprocessable();
        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertUnprocessable();
        $this->postJson($base."/multipart-uploads/{$upload->uuid}/abort")->assertUnprocessable();
    }

    public function test_upload_record_cannot_point_to_legacy_storage_even_when_asset_is_managed(): void
    {
        $upload = $this->upload();
        $upload->update(['storage_disk' => 's3_concerts_legacy']);
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldNotReceive('head', 'completeMultipart'));
        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertUnprocessable();
    }

    public function test_upload_disk_alias_cannot_target_the_legacy_bucket(): void
    {
        $upload = $this->upload();
        config(['filesystems.disks.s3_concerts.bucket' => 'same-bucket', 'filesystems.disks.s3_concerts_legacy.bucket' => 'same-bucket']);
        $this->postJson($this->completeUrl($upload), $this->completion($upload))->assertUnprocessable();
    }

    public function test_legacy_listing_uses_exact_directory_prefix_and_filters_neighbours(): void
    {
        $this->upload();
        $concert = Concert::factory()->create(['storage_disk' => 's3_concerts_legacy', 'storage_prefix' => '/concert-42/']);
        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('listObjects')->once()->with('s3_concerts_legacy', 'concert-42/', 100, null)
                ->andReturn(['objects' => [
                    ['key' => 'concert-42/ours.mp4', 'size' => 12, 'last_modified' => null],
                    ['key' => 'concert-420/theirs.mp4', 'size' => 12, 'last_modified' => null],
                ], 'next_token' => null]);
        });

        $this->getJson("/api/staff/concerts/{$concert->uuid}/legacy-media")
            ->assertOk()->assertJsonCount(1, 'data.objects')->assertJsonPath('data.objects.0.filename', 'ours.mp4');
    }

    private function upload(): MediaUpload
    {
        $user = User::factory()->staff()->create();
        $this->actingAsApiUser($user, ['concert-media:read', 'concert-media:upload']);
        $collection = MediaCollection::factory()->create(['media_type' => MediaType::Video, 'catalogue_mode' => MediaCatalogueMode::Managed]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'storage_disk' => 's3_concerts', 'status' => MediaAssetStatus::Processing, 'verified_at' => null,
        ]);

        return MediaUpload::query()->create([
            'media_asset_id' => $asset->id, 'user_id' => $user->id, 'kind' => MediaUploadKind::Multipart,
            'status' => MediaUploadStatus::Pending, 'storage_disk' => 's3_concerts',
            'storage_key' => $collection->uuid.'/media/'.$asset->uuid.'/original/video.mp4',
            'relative_path' => 'original/video.mp4', 'provider_upload_id' => 'provider-id',
            'size_bytes' => 5242880, 'checksum_algorithm' => 'CRC64NVME', 'checksum' => base64_encode('cccccccc'),
        ]);
    }

    private function objectMetadata(MediaUpload $upload): array
    {
        return ['size' => $upload->size_bytes, 'checksum_crc64nvme' => $upload->checksum];
    }

    private function completeUrl(MediaUpload $upload): string
    {
        return "/api/staff/media-assets/{$upload->asset->uuid}/multipart-uploads/{$upload->uuid}/complete";
    }

    private function completion(MediaUpload $upload): array
    {
        return ['parts' => [['part_number' => 1, 'etag' => 'part-etag', 'checksum_crc64nvme' => $upload->checksum]], 'checksum_crc64nvme' => $upload->checksum];
    }
}
