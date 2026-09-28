<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertDirectorReview;
use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Services\ConcertPhotoReleases;
use App\Features\Programmes\Models\ConcertVideoBatch;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertPhotoUploadStatus;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcertDirectorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_director_review_corrections_email_and_release_are_linked(): void
    {
        [$admin, $director, $event, $concert, $batch] = $this->fixture();
        $initial = app(ConcertTracker::class)->detail($event, 'director_corrections')['status'];
        $this->assertSame('Pending', $initial['label']);
        $this->assertSame('ready', $initial['tone']);
        $reviewUrl = route('customer.concert-review.show', $event);
        $releaseUrl = route('customer.concert-review.release', $event);
        $payload = ['revision' => 0, 'batch_uuid' => $batch->uuid, 'confirm_release' => true];
        $outsider = User::factory()->customer()->create();
        $this->actingAs($outsider)->get($reviewUrl)->assertForbidden();
        $this->post($releaseUrl, $payload)->assertForbidden();
        $this->actingAs($director)->get($reviewUrl)->assertOk()->assertSee('Release concert video')->assertDontSee('Access Code:');
        $file = $batch->files()->first();
        $this->get(route('customer.concert-review.video', [$event, $file->uuid]))->assertOk();
        $this->get(route('concerts.show', $concert))->assertNotFound();
        $this->post(route('customer.concert-review.feedback', $event), $payload + ['feedback' => 'Opening.mp4 at 00:12: correct the title.'])->assertSessionHasNoErrors();
        $this->post($releaseUrl, $payload)->assertConflict();
        $payload['revision'] = 1;
        $this->post($releaseUrl, $payload)->assertUnprocessable();
        $taskUrl = route('admin.concert-tracker.tasks.show', [$event, 'director_corrections']);
        $this->actingAs($admin)->get($taskUrl)->assertOk()->assertSee('Opening.mp4 at 00:12')->assertSee('Tracker task controls');
        $this->patch(route('admin.concert-tracker.corrections.update', $event), $payload + ['corrections_complete' => true, 'corrections_uploaded' => true])
            ->assertRedirect(route('admin.concert-tracker.tasks.show', [$event, 'director_corrections', 'email' => 1]));
        $this->get($taskUrl.'?email=1')->assertOk()->assertSee('REVIEW UPDATED CONCERT')->assertSee($reviewUrl);
        $this->assertSame('in_progress', app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status']['key']);
        $this->assertSame('Corrections', app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status']['label']);
        $this->assertSame('overdue', app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status']['tone']);
        $emailUrl = route('admin.concert-tracker.tasks.email.send', [$event, 'director_corrections']);
        $this->post($emailUrl, ['recipient_email' => $director->email])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('complete', app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status']['key']);
        $this->assertSame('Completed', app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status']['label']);
        $this->post($emailUrl, ['recipient_email' => $director->email])->assertSessionHasErrors('email');
        $this->actingAs($director)->post($releaseUrl, $payload)->assertSessionHasNoErrors();
        $concert->refresh();
        $this->assertTrue($concert->isPubliclyAvailable());
        $this->assertSame($director->id, $concert->approved_by_user_id);
        $this->assertTrue($concert->requiresPassword());
        $this->get($reviewUrl)->assertOk()->assertSee('Access Code:');
        $this->assertSame('complete', app(ConcertTracker::class)->detail($event->fresh(), 'final_release')['status']['key']);
        $this->actingAs($outsider)->get(route('concerts.show', $concert))->assertOk()->assertDontSee('<video', false);
        $this->get(route('concerts.media.stream', [$concert, $file->asset]))->assertForbidden();
        $this->assertSame(1, $concert->mediaCollections()->where('status', 'published')->count());
    }

    public function test_new_feedback_invalidates_pending_correction_email_and_office_contacts_cannot_release(): void
    {
        [$admin, $director, $event, $concert, $batch] = $this->fixture();
        $reviews = app(ConcertDirectorReviews::class);
        $input = ['revision' => 0, 'batch_uuid' => $batch->uuid, 'feedback' => 'Fix first title'];
        $reviews->feedback($event, $director, $input);
        $input['revision'] = 1;
        $reviews->checks($event, $admin, $input + ['corrections_complete' => true, 'corrections_uploaded' => true]);
        $this->actingAs($admin)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'director_corrections']), ['recipient_email' => $director->email])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        $reviews->feedback($event, $director, $input + []);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertNull(ConcertDirectorReview::first()->corrections_completed_at);
        Mail::assertNothingSent();
        StudioAccountAccess::where('user_id', $director->id)->update(['role' => 'office_contact']);
        $this->actingAs($director)->get(route('customer.concert-review.show', $event))->assertForbidden();
        $this->assertFalse($concert->fresh()->isPubliclyAvailable());
    }

    public function test_photo_release_requires_upload_completion_and_announces_only_to_registered_accounts_once(): void
    {
        [$admin, $director, $event, $concert] = $this->fixture();
        $this->travelTo(now()->setDate(2026, 10, 10)->startOfDay());
        $event->concertBookingItem->update(['event_date' => '2026-10-12', 'finishes_at' => '21:00']);
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'photo_release')['status'];
        $this->assertSame('2026-10-14 21:00', $status['dueDate']->format('Y-m-d H:i'));
        $this->assertSame('Pending', $status['label']);
        $album = MediaCollection::create(['concert_id' => $concert->id, 'name' => 'Concert photos', 'media_type' => 'photo',
            'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private', 'storage_disk' => 'local', 'storage_prefix' => 'photos/']);
        MediaAsset::create(['media_collection_id' => $album->id, 'media_type' => 'photo', 'storage_disk' => 'local', 'storage_key' => 'photo.jpg', 'status' => 'available', 'is_visible' => true]);
        $group = app(ConcertPhotoReleases::class)->create($admin, ['name' => 'Studio photos', 'collection_uuids' => [$album->uuid]]);
        $release = route('admin.concert-photo-releases.release', $group);
        $page = route('admin.concert-tracker.tasks.show', [$event, 'photo_release']);
        $this->actingAs($admin)->get($page)->assertOk()->assertSee('Gallery cannot be released yet')
            ->assertSee('Not ready to release')->assertSee('all required photographers must confirm Finished upload');
        $this->from($page)->post($release)->assertRedirect($page)->assertSessionHasErrors('release');
        $this->mock(ConcertPhotoUploadStatus::class)->shouldReceive('forEvent')->andReturn(['key' => 'complete', 'label' => 'Complete', 'detail' => '', 'sourceUrl' => null, 'completedAt' => now(), 'photoRoles' => []]);
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'photo_release')['status'];
        $this->assertSame('Ready to release', $status['label']);
        $this->assertSame('overdue', $status['tone']);
        $this->post($release)->assertRedirect(route('admin.concert-photo-releases.email', $group));
        $this->assertSame('complete', app(ConcertTracker::class)->detail($event->fresh(), 'photo_release')['status']['key']);
        $registered = User::factory()->customer()->create();
        $unregistered = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $registered->id, 'studio_id' => $event->studio_id, 'access_verified_at' => now()]);
        $url = route('admin.concert-photo-releases.email', $group);
        $this->get($url)->assertOk()->assertSee($registered->email)->assertDontSee($unregistered->email)->assertSee('VIEW GALLERY');
        $send = route('admin.concert-photo-releases.email.send', $group);
        $this->post($send, ['confirm_send' => true])->assertSessionHasNoErrors();
        $this->post($send, ['confirm_send' => true])->assertSessionHasNoErrors();
        $deliveries = ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->get();
        $this->assertCount(1, $deliveries);
        $this->assertSame($registered->email, $deliveries->first()->recipient_email);
        (new SendConcertTrackerEmail($deliveries->first()->id))->handle();
        $this->post($send, ['confirm_send' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, ConcertTrackerEmailDelivery::where('photo_release_group_id', $group->id)->count());
        $this->actingAs($registered)->post($send, ['confirm_send' => true])->assertForbidden();
    }

    public function test_approval_email_shows_missing_requirements_and_stale_sends_return_validation(): void
    {
        [$admin, $director, $event, $concert, $batch] = $this->fixture();
        $batch->update(['completed_at' => null]);
        $page = route('admin.concert-tracker.tasks.show', [$event, 'approval_link_email']);
        $send = route('admin.concert-tracker.tasks.email.send', [$event, 'approval_link_email']);
        $this->actingAs($admin)->get($page)->assertOk()->assertSee('Not ready to send')
            ->assertSee('The uploader has not confirmed Finished Uploading.')
            ->assertDontSee('action="'.$send.'"', false);
        $this->from($page)->post($send, ['recipient_email' => $director->email])
            ->assertRedirect($page)->assertSessionHasErrors('email');
        $this->assertSame(0, ConcertTrackerEmailDelivery::count());
        Mail::assertNothingSent();
    }

    public function test_incomplete_corrected_upload_explains_why_release_and_confirmation_are_blocked(): void
    {
        [$admin, , $event, , $batch] = $this->fixture();
        ConcertDirectorReview::create(['scheduling_event_id' => $event->id, 'revision' => 1]);
        $batch->update(['completed_at' => null]);
        $this->actingAs($admin)->get(route('admin.concert-tracker.tasks.show', [$event, 'director_corrections']))
            ->assertOk()->assertSee('Corrections cannot be confirmed as uploaded yet')
            ->assertSee('The uploader has not confirmed Finished Uploading.');
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'final_release']))
            ->assertOk()->assertSee('Director release is not available yet')->assertSee('Director corrections are outstanding');
    }

    public function test_release_without_feedback_marks_corrections_not_required(): void
    {
        [$admin, $director, $event, $concert, $batch] = $this->fixture();
        app(ConcertDirectorReviews::class)->release($event, $director, ['revision' => 0, 'batch_uuid' => $batch->uuid]);
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'director_corrections')['status'];
        $this->assertSame('No corrections', $status['label']);
        $this->assertSame('not_required', $status['tone']);
    }

    public function test_video_upload_deadline_uses_each_event_with_standard_warning_colours(): void
    {
        [$admin, $director, $event, $concert, $batch] = $this->fixture();
        $event->update(['event_date' => '2026-10-01']);
        $batch->update(['completed_at' => null]);
        // The booking item may be on another date: the deadline follows this event.
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'video_uploaded')['status'];
        $this->assertSame('2026-10-11', $status['dueDate']->toDateString());
        $this->assertSame('ready', $status['tone']);
        $this->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
        $this->assertSame('near-due', app(ConcertTracker::class)->detail($event->fresh(), 'video_uploaded')['status']['tone']);
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $this->assertSame('overdue', app(ConcertTracker::class)->detail($event->fresh(), 'video_uploaded')['status']['tone']);
        $batch->update(['completed_at' => now()]);
        $status = app(ConcertTracker::class)->detail($event->fresh(), 'video_uploaded')['status'];
        $this->assertSame('complete', $status['tone']);
        $this->assertNull($status['dueDate']);
    }

    public function test_hub_separates_review_action_from_released_video_and_hides_it_until_ready(): void
    {
        [$staff, $director, $event, $concert, $batch] = $this->fixture();
        $trackerCode = fn () => app(ConcertTracker::class)->page('date', 2026, 'completion')['releaseLogins'][$event->id]['final_release'];
        $this->assertNull($trackerCode());
        $hubUrl = route('customer.studio-communications.index', $event->studio);
        $reviewUrl = route('customer.concert-review.show', $event);
        $videoUrl = route('concerts.show', $concert);
        $card = fn () => app(StudioCommunicationHistory::class)->forUser($director, $event->studio)['hub']['events']->first();
        $batch->update(['completed_at' => null]);
        $waiting = $card();
        $this->assertNull($waiting['videoReview']['url']);
        $this->assertSame('Not ready', $waiting['videoReview']['status']);
        $this->assertNull($waiting['checklist']->firstWhere('key', 'video_review'));
        $this->assertNull($waiting['production']->firstWhere('label', 'Concert video released')['url']);
        $this->assertNull($waiting['production']->firstWhere('label', 'Concert video released')['loginCode']);
        $this->actingAs($director)->get($hubUrl)->assertOk()
            ->assertSeeInOrder(['Your actions', 'Review &amp; release concert video', 'DancePro progress', 'Concert video released'], false)
            ->assertSee('aria-disabled="true"', false)->assertDontSee('href="'.$reviewUrl.'"', false);

        $batch->update(['completed_at' => now()]);
        $ready = $card();
        $this->assertSame($reviewUrl, $ready['videoReview']['url']);
        $this->assertSame('Ready for review', $ready['videoReview']['status']);
        $this->assertFalse($ready['checklist']->firstWhere('key', 'video_review')['complete']);
        $this->get($hubUrl)->assertOk()->assertSee('href="'.$reviewUrl.'"', false)->assertDontSee('href="'.$videoUrl.'"', false);

        $this->post(route('customer.concert-review.release', $event), ['revision' => 0, 'batch_uuid' => $batch->uuid, 'confirm_release' => true])
            ->assertSessionHasNoErrors();
        $released = $card();
        $this->assertTrue($released['videoReview']['complete']);
        $this->assertNull($released['videoReview']['url']);
        $this->assertTrue($released['checklist']->firstWhere('key', 'video_review')['complete']);
        $milestone = $released['production']->firstWhere('label', 'Concert video released');
        $this->assertSame('complete', $milestone['status']);
        $this->assertSame($videoUrl, $milestone['url']);
        $this->assertNotEmpty($milestone['loginCode']['value']);
        $this->assertSame($milestone['loginCode']['value'], $trackerCode()['value']);
        $response = $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'completion']))->assertOk();
        $doc = new \DOMDocument;
        @$doc->loadHTML($response->getContent());
        $code = (new \DOMXPath($doc))->query('//td[@data-task-key="final_release"]//span[@class="tracker-access-code"]')->item(0);
        $this->assertNotNull($code);
        $this->assertStringContainsString($milestone['loginCode']['value'], $code->textContent);
        $this->actingAs($director);
        $this->get($hubUrl)->assertOk()->assertSee('href="'.$videoUrl.'"', false)
            ->assertSee('Access Code:')->assertSee($milestone['loginCode']['value']);

        // Reopening the upload must withdraw the old release link and code.
        ConcertVideoBatch::create(['scheduling_event_id' => $event->id, 'created_by_user_id' => $director->id, 'manifest_hash' => str_repeat('b', 64)]);
        $reopened = $card();
        $this->assertNull($trackerCode());
        $this->assertNull($reopened['videoReview']['url']);
        $this->assertFalse($reopened['videoReview']['complete']);
        $this->assertNull($reopened['production']->firstWhere('label', 'Concert video released')['loginCode']);
        $this->get($hubUrl)->assertOk()->assertDontSee('href="'.$videoUrl.'"', false)->assertDontSee($milestone['loginCode']['value']);
    }

    public function test_hub_review_waits_for_corrections_and_respects_director_permissions_and_booked_services(): void
    {
        [$admin, $director, $event, , $batch] = $this->fixture();
        $hub = fn () => app(StudioCommunicationHistory::class)->forUser($director, $event->studio)['hub']['events']->first();
        $reviews = app(ConcertDirectorReviews::class);
        $reviews->feedback($event, $director, ['revision' => 0, 'batch_uuid' => $batch->uuid, 'feedback' => 'Correct the title.']);
        $this->assertNull($hub()['videoReview']['url']);
        $this->assertStringContainsString('corrections', $hub()['videoReview']['instruction']);
        $reviews->checks($event, $admin, ['revision' => 1, 'batch_uuid' => $batch->uuid, 'corrections_complete' => true, 'corrections_uploaded' => true]);
        $this->assertNull($hub()['videoReview']['url']);
        $this->actingAs($admin)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'director_corrections']), ['recipient_email' => $director->email])->assertSessionHasNoErrors();
        (new SendConcertTrackerEmail(ConcertTrackerEmailDelivery::latest('id')->firstOrFail()->id))->handle();
        $this->assertNotNull($hub()['videoReview']['url']);
        StudioAccountAccess::where('user_id', $director->id)->update(['role' => 'office_contact']);
        $this->assertNull($hub()['videoReview']['url']);
        $this->assertStringContainsString('owner or director', $hub()['videoReview']['instruction']);
        $event->concertBookingItem->booking->update(['wants_concert_videography' => false]);
        $this->assertNull($hub()['videoReview']);
        $this->assertNull($hub()['production']->firstWhere('label', 'Concert video released'));
    }

    private function fixture(): array
    {
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $director = User::factory()->customer()->create();
        $studio = Studio::factory()->create(['contact_email' => $director->email]);
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $director->id, 'email' => $director->email, 'role' => 'owner', 'status' => 'active']);
        $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => 'Review concert', 'event_type' => 'concert', 'event_date' => today()->addDays(10)]);
        $booking = ConcertBooking::create(['status' => 'approved', 'studio_name' => $studio->name, 'contact_name' => $director->name,
            'contact_email' => $director->email, 'contact_phone' => '0400000000', 'wants_concert_videography' => true, 'wants_concert_photography' => true]);
        $item = ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id,
            'item_type' => 'concert', 'title' => $event->name, 'event_date' => $event->event_date, 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Street']);
        $concert = Concert::factory()->create(['studio_id' => $studio->id, 'concert_booking_item_id' => $item->id, 'event_date' => $event->event_date, 'status' => 'draft', 'requires_approval' => true, 'approved_at' => null, 'published_at' => null, 'available_until' => null]);
        $batch = ConcertVideoBatch::create(['scheduling_event_id' => $event->id, 'created_by_user_id' => $admin->id, 'manifest_hash' => str_repeat('a', 64), 'completed_at' => now(), 'completed_by_user_id' => $admin->id]);
        $album = MediaCollection::create(['concert_id' => $concert->id, 'name' => 'Final videos', 'media_type' => 'video', 'catalogue_mode' => 'managed', 'status' => 'draft', 'visibility' => 'private', 'storage_disk' => 'local', 'storage_prefix' => 'review/']);
        $asset = MediaAsset::create(['media_collection_id' => $album->id, 'media_type' => 'video', 'storage_disk' => 'local', 'storage_key' => 'review/opening.mp4', 'status' => 'available', 'is_visible' => false]);
        Storage::disk('local')->put($asset->storage_key, 'test mp4 bytes');
        $batch->files()->create(['original_filename' => 'Opening.mp4', 'byte_count' => 14, 'sha256' => str_repeat('a', 64), 'storage_disk' => 'local', 'storage_key' => $asset->storage_key, 'media_asset_id' => $asset->id, 'verified_at' => now()]);
        ConcertTrackerTask::create(['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'concert_edited', 'status' => 'complete']);
        $quality = app(ConcertQualityChecklist::class);
        $task = app(ConcertTracker::class)->detail($event, 'quality_check')['task'];
        $quality->save($task, $admin, $quality->keys(), $quality->fingerprint($task));

        return [$admin, $director, $event->fresh(), $concert, $batch];
    }
}
