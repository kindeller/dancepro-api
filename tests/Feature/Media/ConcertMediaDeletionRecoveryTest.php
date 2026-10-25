<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ConcertMediaDeletionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_video_requires_force_confirmation_and_cleans_up_only_its_files_and_uploads(): void
    {
        $asset = $this->processingAsset();
        $multipart = $this->upload($asset);
        $batch = $this->upload($asset, ['kind' => MediaUploadKind::SingleBatch, 'provider_upload_id' => null]);
        $completed = $this->upload($asset, ['status' => MediaUploadStatus::Completed]);
        $other = $this->processingAsset();
        $otherUpload = $this->upload($other);
        Storage::disk('s3_concerts')->put($asset->storage_key, 'partial original');
        Storage::disk('s3_concerts')->put($other->storage_key, 'keep');
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($multipart): void {
            $mock->shouldReceive('abortMultipart')->once()->with('s3_concerts', $multipart->storage_key, $multipart->provider_upload_id);
        });

        $preview = $this->get(route('admin.concerts.media.assets.confirm-delete', $asset))
            ->assertOk()->assertSee('Force delete and clean up')->assertSee($multipart->provider_upload_id)
            ->assertSee('Stop uploading in Flutter');
        $data = ['confirmation' => 'DELETE', 'digest' => $preview->viewData('digest')];
        $this->delete(route('admin.concerts.media.assets.destroy', $asset), $data)->assertSessionHasErrors('force');
        $this->assertSame(MediaAssetStatus::Processing, $asset->fresh()->status);
        Storage::disk('s3_concerts')->assertExists($asset->storage_key);
        $this->delete(route('admin.concerts.media.assets.destroy', $asset), array_merge($data, ['force' => 1]))->assertRedirect();

        $this->assertSoftDeleted($asset);
        $this->assertSame(MediaUploadStatus::Aborted, $multipart->fresh()->status);
        $this->assertSame(MediaUploadStatus::Aborted, $batch->fresh()->status);
        $this->assertSame(MediaUploadStatus::Completed, $completed->fresh()->status);
        $this->assertSame(MediaUploadStatus::Pending, $otherUpload->fresh()->status);
        Storage::disk('s3_concerts')->assertMissing($asset->storage_key);
        Storage::disk('s3_concerts')->assertExists($other->storage_key);
    }

    public function test_force_delete_can_clear_an_empty_reservation_and_a_collection_with_processing_videos(): void
    {
        $asset = $this->processingAsset();
        $this->deleteAsset($asset);
        $this->assertSoftDeleted($asset);

        $asset = $this->processingAsset();
        $upload = $this->upload($asset);
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($upload): void {
            $mock->shouldReceive('abortMultipart')->once()->with('s3_concerts', $upload->storage_key, $upload->provider_upload_id);
        });
        $digest = $this->get(route('admin.concerts.media.collections.confirm-delete', $asset->collection))
            ->assertOk()->viewData('digest');
        $this->delete(route('admin.concerts.media.collections.destroy', $asset->collection), [
            'confirmation' => 'DELETE', 'digest' => $digest, 'force' => 1,
        ])->assertRedirect();
        $this->assertSoftDeleted($asset);
        $this->assertSoftDeleted($asset->collection);
        $this->assertSame(MediaUploadStatus::Aborted, $upload->fresh()->status);
    }

    public function test_new_upload_after_preview_requires_review_before_any_cleanup(): void
    {
        $asset = $this->processingAsset();
        $digest = $this->get(route('admin.concerts.media.assets.confirm-delete', $asset))->assertOk()->viewData('digest');
        $upload = $this->upload($asset);
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldNotReceive('abortMultipart'));

        $this->delete(route('admin.concerts.media.assets.destroy', $asset), [
            'confirmation' => 'DELETE', 'digest' => $digest, 'force' => 1,
        ])->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($asset);
        $this->assertSame(MediaAssetStatus::Processing, $asset->fresh()->status);
        $this->assertSame(MediaUploadStatus::Pending, $upload->fresh()->status);
    }

    public function test_abort_failure_keeps_media_hidden_blocks_uploads_and_allows_retry_after_a_lost_abort_response(): void
    {
        $asset = $this->processingAsset();
        $upload = $this->upload($asset);
        Storage::disk('s3_concerts')->put($asset->storage_key, 'partial original');
        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('abortMultipart')->once()->andThrow(new AwsException('Access denied', new Command('AbortMultipartUpload'), ['code' => 'AccessDenied']));
            $mock->shouldNotReceive('presignPart', 'completeMultipart', 'head');
        });
        $this->deleteAsset($asset)->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($asset);
        $this->assertFalse($asset->fresh()->is_visible);
        $this->assertSame(MediaAssetStatus::Archived, $asset->fresh()->status);
        $this->assertSame(MediaUploadStatus::Pending, $upload->fresh()->status);
        Storage::disk('s3_concerts')->assertExists($asset->storage_key);
        $base = '/admin/media-api/assets/'.$asset->uuid;
        $this->postJson($base.'/finalize', ['expected_outputs' => ['original', 'fallback_mp4']])->assertUnprocessable();
        $this->postJson($base.'/multipart/'.$upload->uuid.'/parts', ['parts' => [[
            'part_number' => 1, 'checksum_crc64nvme' => base64_encode('cccccccc'),
        ]]])->assertUnprocessable();
        $this->patchJson(route('admin.media-api.assets.update', $asset), ['is_visible' => true])->assertUnprocessable();

        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('abortMultipart')->once()->andThrow(new AwsException('Already aborted', new Command('AbortMultipartUpload'), ['code' => 'NoSuchUpload']));
        });
        $this->deleteAsset($asset)->assertRedirect();
        $this->assertSoftDeleted($asset);
        Storage::disk('s3_concerts')->assertMissing($asset->storage_key);
        $this->assertSame(MediaUploadStatus::Aborted, $upload->fresh()->status);
    }

    public function test_collection_cleanup_failure_archives_all_items_and_blocks_new_reservations_and_publication(): void
    {
        $asset = $this->processingAsset();
        $this->upload($asset);
        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('abortMultipart')->once()->andThrow(new AwsException('Access denied', new Command('AbortMultipartUpload'), ['code' => 'AccessDenied']));
        });
        $collection = $asset->collection;
        $digest = $this->get(route('admin.concerts.media.collections.confirm-delete', $collection))->assertOk()->viewData('digest');
        $this->delete(route('admin.concerts.media.collections.destroy', $collection), [
            'confirmation' => 'DELETE', 'digest' => $digest, 'force' => 1,
        ])->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($collection);
        $this->assertSame('archived', $collection->fresh()->status->value);
        $this->assertSame('private', $collection->fresh()->visibility->value);
        $this->assertFalse($asset->fresh()->is_visible);
        $this->patchJson(route('admin.media-api.collections.update', $collection), [
            'status' => 'published', 'show_all_verified' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('collection');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(route('admin.media-api.assets.store', $collection), [
            'media_type' => 'video', 'display_name' => 'New video', 'original_filename' => 'new.mp4',
            'expected_outputs' => ['original', 'fallback_mp4'],
        ])->assertUnprocessable()->assertJsonValidationErrors('collection');
    }

    public function test_files_arriving_during_abort_are_not_deleted_without_a_new_confirmation(): void
    {
        $asset = $this->processingAsset();
        $this->upload($asset);
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($asset): void {
            $mock->shouldReceive('abortMultipart')->once()->andReturnUsing(function () use ($asset): void {
                Storage::disk('s3_concerts')->put($asset->storage_key, 'arrived during abort');
            });
        });
        $this->deleteAsset($asset)->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($asset);
        Storage::disk('s3_concerts')->assertExists($asset->storage_key);
        $this->deleteAsset($asset)->assertRedirect();
        $this->assertSoftDeleted($asset);
        Storage::disk('s3_concerts')->assertMissing($asset->storage_key);
    }

    public function test_partial_object_deletion_keeps_records_and_can_be_retried(): void
    {
        $asset = $this->processingAsset();
        $disk = Storage::disk('s3_concerts');
        $fallback = str_replace('original/video.mp4', 'stream/fallback.mp4', $asset->storage_key);
        $disk->put($asset->storage_key, 'original');
        $disk->put($fallback, 'playback');
        $failOnce = true;
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('delete')->andReturnUsing(function (string $key) use ($disk, $fallback, &$failOnce): bool {
            if ($key === $fallback && $failOnce) {
                $failOnce = false;

                return false;
            }

            return $disk->delete($key);
        });
        Storage::shouldReceive('disk')->with('s3_concerts')->andReturn($proxy);

        $this->deleteAsset($asset)->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($asset);
        $this->assertFalse($asset->fresh()->is_visible);
        $disk->assertMissing($asset->storage_key);
        $disk->assertExists($fallback);
        $this->deleteAsset($asset)->assertRedirect();
        $this->assertSoftDeleted($asset);
        $disk->assertMissing($fallback);
    }

    public function test_mismatched_asset_storage_is_never_deleted(): void
    {
        $asset = $this->processingAsset();
        $asset->update(['storage_disk' => 's3_concerts_legacy']);
        $this->get(route('admin.concerts.media.assets.confirm-delete', $asset))
            ->assertSessionHasErrors('storage_disk');
        $this->assertNotSoftDeleted($asset);
    }

    public function test_force_delete_does_not_cancel_uploads_outside_the_asset_prefix_or_for_non_staff(): void
    {
        $asset = $this->processingAsset();
        $upload = $this->upload($asset, ['storage_key' => 'unrelated/video.mp4']);
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldNotReceive('abortMultipart'));
        $url = route('admin.concerts.media.assets.confirm-delete', $asset);
        $this->get($url)->assertSessionHasErrors('upload');
        $this->assertSame(MediaUploadStatus::Pending, $upload->fresh()->status);
        $this->actingAs(User::factory()->customer()->create());
        $this->get($url)->assertForbidden();
        $this->delete(route('admin.concerts.media.assets.destroy', $asset), [
            'confirmation' => 'DELETE', 'digest' => str_repeat('0', 64), 'force' => 1,
        ])->assertForbidden();
        $this->assertNotSoftDeleted($asset);
    }

    private function processingAsset(): MediaAsset
    {
        Storage::fake('s3_concerts');
        $this->actingAs(User::factory()->staff()->create());
        $collection = MediaCollection::factory()->for(Concert::factory())->create([
            'catalogue_mode' => MediaCatalogueMode::Managed, 'media_type' => MediaType::Video,
            'storage_disk' => 's3_concerts',
        ]);
        $asset = MediaAsset::factory()->for($collection, 'collection')->create([
            'media_type' => MediaType::Video, 'storage_disk' => 's3_concerts',
            'status' => MediaAssetStatus::Processing, 'verified_at' => null, 'is_visible' => false,
        ]);
        $asset->update(['storage_key' => $collection->uuid.'/media/'.$asset->uuid.'/original/video.mp4']);

        return $asset;
    }

    private function upload(MediaAsset $asset, array $attributes = []): MediaUpload
    {
        return MediaUpload::query()->create(array_merge([
            'media_asset_id' => $asset->id, 'user_id' => auth()->id(), 'kind' => MediaUploadKind::Multipart,
            'status' => MediaUploadStatus::Pending, 'storage_disk' => 's3_concerts',
            'storage_key' => $asset->storage_key, 'provider_upload_id' => 'upload-'.Str::uuid(),
        ], $attributes));
    }

    private function deleteAsset(MediaAsset $asset): TestResponse
    {
        $digest = $this->get(route('admin.concerts.media.assets.confirm-delete', $asset))->assertOk()->viewData('digest');

        return $this->delete(route('admin.concerts.media.assets.destroy', $asset), [
            'confirmation' => 'DELETE', 'digest' => $digest, 'force' => 1,
        ]);
    }
}
