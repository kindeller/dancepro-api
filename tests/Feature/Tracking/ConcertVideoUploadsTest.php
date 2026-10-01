<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Services\S3MediaStorage;
use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertVideoReadiness;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ConcertVideoUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_queue_verification_finished_signal_and_studio_inclusions_control_readiness(): void
    {
        [$admin, $event] = $this->fixture();
        $this->mock(S3MediaStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('presignPut')->once()->andReturn(['url' => 'https://uploads.example.test/video', 'headers' => []]);
            $mock->shouldReceive('head')->twice()->andReturn(['size' => 10, 'checksum_sha256' => base64_encode(hex2bin(str_repeat('a', 64)))],
                ['size' => 20, 'checksum_sha256' => base64_encode(hex2bin(str_repeat('b', 64)))]);
        });
        $payload = ['batch_uuid' => (string) Str::uuid(), 'files' => [
            ['file_uuid' => (string) Str::uuid(), 'file_name' => 'Act 1.mp4', 'byte_count' => 10, 'sha256' => str_repeat('a', 64)],
            ['file_uuid' => (string) Str::uuid(), 'file_name' => 'Act 2.mp4', 'byte_count' => 20, 'sha256' => str_repeat('b', 64)],
        ]];
        $base = '/api/staff/events/'.$event->uuid.'/concert-videos/batches';
        $this->apiAs($admin)->postJson($base, $payload)->assertOk()->assertJsonPath('data.queued', 2)->assertJsonPath('data.verified', 0);
        $batch = ConcertVideoBatch::where('uuid', $payload['batch_uuid'])->firstOrFail();
        $path = $base.'/'.$batch->uuid;
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertConflict();
        $this->postJson($path.'/files/'.$payload['files'][0]['file_uuid'].'/address')->assertOk()->assertJsonPath('data.verified', false);
        $this->postJson($path.'/files/'.$payload['files'][0]['file_uuid'].'/verify')->assertOk()->assertJsonPath('data.verified', 1);
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertConflict();
        $this->postJson($path.'/files/'.$payload['files'][1]['file_uuid'].'/verify')->assertOk()->assertJsonPath('data.verified', 2);
        $this->assertFalse(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $this->postJson($path.'/complete', ['all_items_included' => false])->assertUnprocessable();
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertOk();
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertOk();
        $this->assertSame(2, MediaAsset::count());
        $this->assertSame(0, MediaAsset::where('is_visible', true)->count());
        $this->assertSame($admin->id, $batch->fresh()->completed_by_user_id);
        ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'concert_edited', 'status' => 'complete']);
        $quality = app(ConcertQualityChecklist::class);
        $task = app(ConcertTracker::class)->detail($event, 'quality_check')['task'];
        $quality->save($task, $admin, $quality->keys(), $quality->fingerprint($task));
        $studioFile = StudioEventFile::create(['scheduling_event_id' => $event->id, 'kind' => 'studio_video', 'status' => 'approved', 'is_current' => true,
            'disk' => 'local', 'storage_key' => 'opening.mp4', 'original_name' => 'Opening slideshow.mp4', 'size_bytes' => 123,
            'mime_type' => 'video/mp4', 'uploaded_by_user_id' => $admin->id, 'uploaded_at' => now()]);
        $this->assertFalse(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $inclusions = route('admin.concert-tracker.video-inclusions.update', $event);
        $this->actingAs($admin, 'web')->patch($inclusions, ['batch_uuid' => $batch->uuid, 'inclusions' => [$studioFile->uuid => $payload['files'][0]['file_uuid']]])->assertSessionHasNoErrors();
        $this->assertSame($payload['files'][0]['file_uuid'], $batch->fresh()->studio_inclusions[$studioFile->uuid]['file_uuid'] ?? null);
        $ready = app(ConcertVideoReadiness::class)->page($event);
        $this->assertTrue($ready['ready'], implode(' ', $ready['blockers']));
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'video_uploaded']))->assertOk()->assertSee('Ready to send to the studio director.')->assertSee('Opening slideshow.mp4')->assertSee('2 of 2 videos verified');
        $studioFile->update(['status' => 'pending']);
        $this->assertFalse(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $studioFile->update(['status' => 'approved', 'is_current' => false]);
        $replacement = $studioFile->replicate(['uuid']);
        $replacement->is_current = true;
        $replacement->save();
        $this->assertFalse(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $new = $payload;
        $new['batch_uuid'] = (string) Str::uuid();
        foreach ($new['files'] as &$file) {
            $file['file_uuid'] = (string) Str::uuid();
        } unset($file);
        $this->apiAs($admin)->postJson($base, $new)->assertOk()->assertJsonPath('data.verified', 2);
        $this->assertFalse(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertConflict();
        $this->actingAs($admin, 'web')->patchJson($inclusions, ['batch_uuid' => $batch->uuid, 'inclusions' => []])->assertConflict();
    }

    public function test_only_admin_staff_or_assigned_editor_can_upload_and_bad_files_cannot_complete(): void
    {
        [$admin, $event] = $this->fixture();
        $editor = User::factory()->crew()->create();
        CrewProfile::factory()->create(['user_id' => $editor->id]);
        $base = '/api/staff/events/'.$event->uuid.'/concert-videos/batches';
        $payload = ['batch_uuid' => (string) Str::uuid(), 'files' => [
            ['file_uuid' => (string) Str::uuid(), 'file_name' => 'Concert.mp4', 'byte_count' => 10, 'sha256' => str_repeat('a', 64)],
        ]];
        $this->apiAs($editor)->postJson($base, $payload)->assertForbidden();
        ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'concert_edited', 'status' => 'in_progress', 'assignee_user_id' => $editor->id]);
        $bad = $payload;
        $bad['files'][0]['file_name'] = '../Concert.mp4';
        $this->postJson($base, $bad)->assertUnprocessable();
        $bad['files'][0]['file_name'] = 'Concert.mp4';
        $bad['files'][0]['byte_count'] = 5368709121;
        $this->postJson($base, $bad)->assertUnprocessable();
        $this->postJson($base, $payload)->assertOk();
        $path = $base.'/'.$payload['batch_uuid'];
        $this->mock(S3MediaStorage::class, fn (MockInterface $mock) => $mock->shouldReceive('head')->once()->andReturn(['size' => 10, 'checksum_sha256' => 'wrong']));
        $this->postJson($path.'/files/'.$payload['files'][0]['file_uuid'].'/verify')->assertConflict();
        $this->postJson($path.'/complete', ['all_items_included' => true])->assertConflict();
        $this->apiAs($admin)->postJson($path.'/complete', ['all_items_included' => true])->assertNotFound();
        $this->apiAs(User::factory()->customer()->create())->postJson($base, $payload)->assertForbidden();
    }

    private function apiAs(User $user): static
    {
        $user->withAccessToken($user->createToken('Upload test', ['concert-media:upload'])->accessToken);

        return $this->actingAs($user, 'sanctum');
    }

    public function test_signed_local_concert_upload_transfers_verifies_and_prevents_overwriting_verified_files(): void
    {
        [$admin, $event] = $this->fixture();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withoutMiddleware(PreventRequestForgery::class);
        config()->set('filesystems.disks.s3_concerts.driver', 'local');
        $bytes = 'local concert mp4';
        $base = '/api/staff/events/'.$event->uuid.'/concert-videos/batches';
        $payload = ['batch_uuid' => (string) Str::uuid(), 'files' => [
            ['file_uuid' => (string) Str::uuid(), 'file_name' => 'Concert.mp4', 'byte_count' => strlen($bytes), 'sha256' => hash('sha256', $bytes)],
        ]];
        $this->apiAs($admin)->postJson($base, $payload)->assertOk();
        $path = $base.'/'.$payload['batch_uuid'].'/files/'.$payload['files'][0]['file_uuid'];
        $url = $this->postJson($path.'/address')->assertOk()->json('data.upload.url');
        $this->call('PUT', $url.'&key=tampered', [], [], [], [], $bytes)->assertForbidden();
        $this->call('PUT', $url, [], [], [], [], $bytes)->assertOk();
        $this->postJson($path.'/verify')->assertOk()->assertJsonPath('data.verified', 1);
        $this->call('PUT', $url, [], [], [], [], $bytes)->assertNotFound();
        $batch = ConcertVideoBatch::where('uuid', $payload['batch_uuid'])->firstOrFail();
        Storage::disk('s3_concerts')->assertExists($batch->files()->first()->storage_key);
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        $this->postJson($base.'/'.$batch->uuid.'/complete', ['all_items_included' => true])->assertOk();
        $concert = $event->concertBookingItem->concert;
        $concert->update(['event_date' => $event->event_date]);
        $event->update(['studio_id' => $concert->studio_id]);
        $director = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $concert->studio_id, 'user_id' => $director->id,
            'email' => $director->email, 'role' => 'owner', 'status' => 'active']);
        ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'concert_edited', 'status' => 'complete']);
        $quality = app(ConcertQualityChecklist::class);
        $task = app(ConcertTracker::class)->detail($event, 'quality_check')['task'];
        $quality->save($task, $admin, $quality->keys(), $quality->fingerprint($task));
        $event = $event->fresh();
        $this->assertTrue(app(ConcertVideoReadiness::class)->page($event)['ready']);
        $this->actingAs($admin, 'web')->post(route('admin.concert-tracker.tasks.email.send', [$event, 'approval_link_email']),
            ['recipient_email' => $director->email])->assertRedirect()->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->actingAs($director, 'web')->get(route('customer.concert-review.show', $event))->assertOk();
        $this->get(route('concerts.show', $concert))->assertNotFound();
        $file = $batch->files()->first();
        $this->get(route('customer.concert-review.video', [$event, $file->uuid]))->assertOk();
        $this->post(route('customer.concert-review.release', $event), ['revision' => 0, 'batch_uuid' => $batch->uuid, 'confirm_release' => true])
            ->assertSessionHasNoErrors();
        $concert->refresh();
        $this->assertTrue($concert->isPubliclyAvailable());
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $concert->studio_id, 'access_verified_at' => now()]);
        $this->actingAs($customer, 'web')->get(route('concerts.media.stream', [$concert, $file->asset]))->assertForbidden();
        $this->post(route('customer.media.concerts.store'), ['year' => $concert->event_date->year, 'concert_code' => $concert->codeGroup->code_encrypted])->assertSessionHasNoErrors();
        $this->getJson(route('concerts.media.playback', [$concert, $file->asset]))->assertOk()->assertJsonPath('data.format', 'progressive');
        $this->get(route('concerts.media.download', [$concert, $file->asset]))->assertDownload('Concert.mp4');
        $this->assertSame($bytes, Storage::disk('s3_concerts')->get($file->storage_key));

    }

    private function fixture(): array
    {
        Storage::fake('s3_concerts');
        $admin = User::factory()->admin()->create();
        $event = SchedulingEvent::create(['name' => 'Upload concert', 'event_type' => 'concert', 'event_date' => today()->addDays(10)]);
        $booking = ConcertBooking::create(['status' => 'approved', 'studio_name' => 'Upload studio', 'contact_name' => 'Director',
            'contact_email' => 'upload@example.test', 'contact_phone' => '0400000000', 'wants_concert_videography' => true]);
        $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id,
            'item_type' => 'concert', 'title' => $event->name, 'event_date' => $event->event_date, 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Street']);
        Concert::factory()->create(['concert_booking_item_id' => $item->id]);

        return [$admin, $event];
    }
}
