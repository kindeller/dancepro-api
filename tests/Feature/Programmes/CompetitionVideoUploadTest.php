<?php

namespace Tests\Feature\Programmes;

use App\Features\Crew\Models\CrewRole;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionVideoHolds;
use App\Features\Programmes\Services\CompetitionVideoUploads;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CompetitionVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_batch_and_hold_release_the_complete_clip_set_and_pending_package(): void
    {
        Storage::fake('s3_competitions');
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Video event', 'event_type' => 'competition', 'event_date' => now()->toDateString()]);
        $role = CrewRole::where('code', 'competition-videographer')->firstOrFail();
        $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $nodes = [
            ['uuid' => $day, 'kind' => 'day', 'title' => 'Friday', 'date' => now()->toDateString()],
            ['uuid' => $section, 'kind' => 'section', 'number' => '07', 'title' => 'Jazz', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'number' => '03', 'title' => 'Jessica', 'parent_uuid' => $section],
        ];
        $snapshot = ['event_type' => 'competition', 'event_code' => 'EDC926', 'nodes' => $nodes];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create(['programme_id' => $programme->id, 'programme_revision_id' => $revision->id,
            'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        $this->actingAs($staff)->get(route('admin.competition-videos.events'))
            ->assertRedirect(route('admin.competition.objects.index'));
        $this->actingAs($staff)->get(route('admin.competition-videos.index', $event))->assertOk();
        $order = Order::factory()->paid()->create(['user_id' => $customer->id,
            'metadata' => ['kind' => 'competition', 'source_uuid' => $event->uuid]]);
        $videoItem = OrderItem::create(['order_id' => $order->id, 'item_type' => 'video', 'quantity' => 1,
            'unit_price_amount' => 1800, 'total_price_amount' => 1800,
            'metadata' => ['item_uuid' => $item, 'product' => 'routine_package', 'asset_uuids' => [], 'media_pending' => true]]);
        $bytes = 'verified video bytes';
        $digest = hash('sha256', $bytes);
        $secondDigest = hash('sha256', 'EDC926_07_03a.mp4');
        $this->mock(S3MediaStorage::class, function (MockInterface $mock) use ($digest, $secondDigest, $bytes): void {
            $mock->shouldReceive('presignPut')->twice()->andReturn(['url' => 'https://uploads.example.test/video', 'headers' => []]);
            $mock->shouldReceive('head')->twice()->andReturn(
                ['size' => strlen($bytes), 'content_type' => 'video/mp4', 'checksum_sha256' => base64_encode(hex2bin($digest)), 'checksum_crc64nvme' => null],
                ['size' => strlen($bytes), 'content_type' => 'video/mp4', 'checksum_sha256' => base64_encode(hex2bin($secondDigest)), 'checksum_crc64nvme' => null],
            );
        });
        $uploads = app(CompetitionVideoUploads::class);
        $batch = $uploads->begin($event, $staff, ['batch_uuid' => (string) Str::uuid(), 'plan_uuid' => $plan->uuid,
            'role_uuid' => $role->uuid]);
        foreach (['EDC926_07_03.mp4', 'EDC926_07_03a.mp4'] as $name) {
            $hash = $name === 'EDC926_07_03.mp4' ? $digest : hash('sha256', $name);
            $reserved = $uploads->reserve($event, $batch, ['file_uuid' => (string) Str::uuid(),
                'file_name' => $name, 'sha256' => $hash, 'byte_count' => strlen($bytes)]);
            $this->assertSame($item, $reserved['file']->item_uuid);
            $this->assertSame('s3_competitions', $reserved['file']->storage_disk);
            $this->assertSame('2026/EDC926/'.$name, $reserved['file']->storage_key);
            $this->assertFalse($reserved['already_uploaded']);
            $uploads->verify($event, $batch, $reserved['file']);
        }
        app(CompetitionVideoHolds::class)->set($event, $staff, $item, true);
        $summary = $uploads->complete($event, $batch);
        $this->assertSame(2, $summary['verified']);
        $this->assertFalse(app(CustomerCompetitionCart::class)->videoAvailable($event, $item));
        $this->assertNull($videoItem->fresh()->media_collection_id);

        app(CompetitionVideoHolds::class)->set($event, $staff, $item, false);
        $this->assertTrue(app(CustomerCompetitionCart::class)->videoAvailable($event, $item));
        $videoItem->refresh();
        $this->assertNotNull($videoItem->media_collection_id);
        $this->assertCount(2, $videoItem->metadata['asset_uuids']);
        $entitlement = $videoItem->mediaEntitlements()->firstOrFail();
        $this->assertNotNull($entitlement->ready_at);
        $this->assertCount(2, $entitlement->assets);
        $this->assertNotNull($entitlement->expires_at);
        $first = $entitlement->assets->first();
        $this->assertTrue($first->fresh()->is_visible);
        $this->assertSame('published', $first->collection->status->value);
        $this->assertSame('customer', $first->collection->visibility->value);
        $this->assertSame(1, MediaAsset::query()->whereKey($first->id)
            ->where('media_type', $entitlement->media_type)->where('status', 'available')->where('is_visible', true)
            ->whereHas('collection', fn ($query) => $query->where('status', 'published')->whereIn('visibility', ['customer', 'public']))->count());
        $this->assertSame($first->id, app(AuthorizePurchasedMediaDownload::class)->execute($customer, $entitlement, $first)->id);
        app(CompetitionVideoHolds::class)->set($event, $staff, $item, true);
        try {
            app(AuthorizePurchasedMediaDownload::class)->execute($customer, $entitlement, $first);
            $this->fail('A held video was downloadable.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(3, DB::table('competition_video_hold_changes')->count());
        $this->actingAs($staff)->get(route('admin.competition-videos.index', $event))
            ->assertOk()->assertSee('EDC926_07_03a.mp4');

        $existingName = 'EDC926_99_99.mp4';
        Storage::disk('s3_competitions')->put('2026/EDC926/'.$existingName, 'existing live video');
        $laterBatch = $uploads->begin($event, $staff, ['batch_uuid' => (string) Str::uuid(),
            'plan_uuid' => $plan->uuid, 'role_uuid' => $role->uuid]);
        try {
            $uploads->reserve($event, $laterBatch, ['file_uuid' => (string) Str::uuid(),
                'file_name' => $existingName, 'sha256' => hash('sha256', 'different bytes'), 'byte_count' => 15]);
            $this->fail('An existing Competition Media video could be replaced.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }
        Storage::disk('s3_competitions')->assertExists('2026/EDC926/'.$existingName);
    }
}
