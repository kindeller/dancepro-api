<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\CurrentBookingTimeline;
use App\Features\Concerts\Actions\ManageConcertCodeGroup;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Media\Models\MediaCollection;
use App\Features\Studios\Actions\SaveStudioGalleryCode;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Actions\RehearseConcertEmail;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Database\Seeders\BookingDeliveryRehearsalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingDeliveryConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_approval_preserves_studio_and_prepares_private_media_for_portraits_and_concerts(): void
    {
        [$booking, $studio, $staff] = $this->fixture();
        $studio->update(['name' => 'Renamed studio']);
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);

        foreach ($booking->fresh('items.schedulingEvent', 'items.concert')->items as $item) {
            $this->assertSame($studio->id, $item->schedulingEvent->studio_id);
            $this->assertSame($studio->id, $item->concert->studio_id);
            $this->assertSame('draft', $item->concert->status->value);
            $this->assertFalse($item->concert->is_enabled);
            $this->assertFalse($item->concert->isPubliclyAvailable());
            $this->assertSame($item->item_type->value === 'concert', $item->concert->requires_approval);
        }
        $this->assertDatabaseCount('concerts', 3);
        app(ApproveConcertBooking::class)->execute($booking->fresh(), $staff, null);
        $this->assertDatabaseCount('concerts', 3);
    }

    public function test_approval_rejects_a_different_studio_without_creating_an_event(): void
    {
        [$booking, , $staff] = $this->fixture();
        try {
            app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff, Studio::factory()->create());
            $this->fail('Conflicting studio was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('studio_uuid', $exception->errors());
        }
        $this->assertDatabaseCount('scheduling_events', 0);
        $this->assertSame('pending', $booking->items->first()->fresh()->approval_status);
    }

    public function test_current_timeline_recalculates_without_changing_original_submission(): void
    {
        [$booking, $studio] = $this->fixture();
        $original = $booking->fresh()->submission_snapshot;
        $booking->items[0]->update(['event_date' => '2026-11-20', 'title' => 'Rescheduled portraits']);
        $booking->items[2]->update(['cancelled_at' => now()]);
        $current = app(CurrentBookingTimeline::class)->forBooking($booking->fresh(), $studio);

        $this->assertSame(140000, $current['quote']['photo_minimum_cents']);
        $this->assertSame(150000, $current['quote']['video_quote_cents']);
        $this->assertCount(2, $current['details']['items']);
        $this->assertSame('Rescheduled portraits', $current['details']['items'][0]['title']);
        $this->assertSame($original, $booking->fresh()->submission_snapshot);
        $this->assertSame(200000, $original['quote']['photo_minimum_cents']);
        $this->assertSame(300000, $original['quote']['video_quote_cents']);
    }

    public function test_hub_and_tracker_use_actual_release_state_including_portraits(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.concert', 'items.schedulingEvent');
        $portrait = $booking->items[0];
        MediaCollection::create(['concert_id' => $portrait->concert->id, 'name' => 'Portrait album',
            'storage_disk' => 'local', 'storage_prefix' => 'rehearsal/', 'media_type' => 'photo', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);
        $video = $booking->items[1];
        $video->concert->update(['status' => 'published', 'published_at' => now(), 'approved_at' => now(), 'is_enabled' => false]);

        $hub = app(StudioCommunicationHistory::class)->forUser($customer, $studio)['hub']['events'];
        $portraitCard = $hub->first(fn ($row) => $row['event']->id === $portrait->scheduling_event_id);
        $this->assertSame('complete', $portraitCard['production']->firstWhere('label', 'Photo gallery released')['status']);
        $videoCard = $hub->first(fn ($row) => $row['event']->id === $video->scheduling_event_id);
        $this->assertSame('waiting', $videoCard['production']->firstWhere('label', 'Review & release concert video')['status']);
        $states = app(ConcertTracker::class)->statusesForEvents(collect([$video->schedulingEvent]));
        $this->assertSame('not_started', $states[$video->scheduling_event_id]['final_release']['key']);
        $this->assertDatabaseCount('concert_tracker_tasks', 0);
        $this->actingAs($customer)->get(route('customer.studio-bookings.show', [$studio, $booking]))
            ->assertOk()->assertSee('Current progress')->assertDontSee('/admin/', false);
    }

    public function test_unsent_emails_are_hidden_and_failed_captured_mail_can_retry(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        [$booking, $studio, $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $event = $booking->fresh('items.schedulingEvent')->items[1]->schedulingEvent;
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];
        $delivery = ConcertTrackerEmailDelivery::create([
            'scheduling_event_id' => $event->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail()->id,
            'created_by_user_id' => $staff->id, 'recipient_email' => $customer->email,
            'subject' => 'Rehearsal message', 'body' => 'This message stays local.',
            'status' => 'scheduled', 'scheduled_at' => now()->addHour(), 'is_simulated' => true,
        ]);
        $history = app(StudioCommunicationHistory::class)->forUser($customer, $studio);
        $this->assertCount(0, $history['communications']);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        Mail::assertNothingSent();
        $delivery->update(['status' => 'failed', 'scheduled_at' => null, 'failed_at' => now(), 'failure_message' => 'Test interruption']);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->failed_at);
        $this->assertTrue($delivery->fresh()->is_simulated);
        Mail::assertSent(ConcertTrackerMessage::class, 1);
        $history = app(StudioCommunicationHistory::class)->forUser($customer, $studio);
        $this->assertSame('Test · sent', $history['communications'][0]['status']);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        Mail::assertSent(ConcertTrackerMessage::class, 1);
    }

    public function test_cancelled_event_does_not_send_pending_mail(): void
    {
        Mail::fake();
        [$booking, , $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $event = $booking->fresh('items.schedulingEvent')->items[1]->schedulingEvent;
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];
        $delivery = ConcertTrackerEmailDelivery::create([
            'scheduling_event_id' => $event->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail()->id,
            'recipient_email' => $customer->email, 'subject' => 'Cancelled', 'body' => 'Do not send.', 'status' => 'queued',
        ]);
        $event->update(['cancelled_at' => now()]);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_simulated_message_cannot_be_replayed_through_live_transport(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'external.example.test']);
        [$booking, , $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $event = $booking->fresh('items.schedulingEvent')->items[1]->schedulingEvent;
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];
        $delivery = ConcertTrackerEmailDelivery::create([
            'scheduling_event_id' => $event->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail()->id,
            'recipient_email' => $customer->email, 'subject' => 'Test', 'body' => 'Local only.', 'status' => 'queued', 'is_simulated' => true,
        ]);
        try {
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->fail('A rehearsal message was allowed to use a live transport.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cannot be replayed', $exception->getMessage());
        }
        Mail::assertNothingSent();
    }

    public function test_rehearsal_seed_is_repeatable_without_resetting_review_progress(): void
    {
        User::factory()->customer()->create(['email' => 'studio-director@example.test']);
        $this->seed(BookingDeliveryRehearsalSeeder::class);
        $booking = ConcertBooking::where('uuid', '91000000-0000-4000-8000-000000000010')->firstOrFail();
        $booking->update(['internal_review_note' => 'Reviewed during rehearsal']);
        $this->seed(BookingDeliveryRehearsalSeeder::class);
        $this->assertDatabaseCount('concert_bookings', 3);
        $this->assertDatabaseCount('concert_booking_items', 5);
        $this->assertSame('Reviewed during rehearsal', $booking->fresh()->internal_review_note);
    }

    public function test_selected_scheduled_rehearsal_uses_a_temporary_clock_only_for_that_message(): void
    {
        Mail::fake();
        [$booking, , $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $event = $booking->fresh('items.schedulingEvent')->items[1]->schedulingEvent;
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];
        $attributes = [
            'scheduling_event_id' => $event->id, 'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail()->id,
            'created_by_user_id' => $staff->id, 'recipient_email' => $customer->email,
            'subject' => 'Scheduled rehearsal', 'body' => 'Local only.', 'status' => 'scheduled',
            'is_simulated' => true, 'scheduled_at' => now()->addDays(2),
        ];
        $selected = ConcertTrackerEmailDelivery::create($attributes);
        $untouched = ConcertTrackerEmailDelivery::create($attributes);
        $this->app->instance('env', 'local');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mailpit', 'mail.mailers.smtp.url' => null]);
        $today = today()->toDateString();
        app(RehearseConcertEmail::class)->execute($selected->uuid, now()->addDays(3)->toIso8601String());
        $this->assertSame('sent', $selected->fresh()->status);
        $this->assertSame('scheduled', $untouched->fresh()->status);
        $this->assertSame($today, today()->toDateString());
        Mail::assertSent(ConcertTrackerMessage::class, 1);
    }

    public function test_hub_shows_current_photo_and_video_codes_only_to_studio_managers(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        app(SaveStudioGalleryCode::class)->execute($studio, $staff, 'PHOTO-HUB-TEST', 0);
        $booking->refresh()->load('items.concert', 'items.schedulingEvent');
        $first = $booking->items[1]->concert;
        $first->update(['access_password_hash' => 'VIDEO-HUB-TEST', 'access_password_encrypted' => 'VIDEO-HUB-TEST']);
        $group = app(ManageConcertCodeGroup::class)->create($studio, $staff, [
            'year' => 2026, 'name' => 'Shared video code', 'code' => 'GROUP-HUB-TEST', 'concert_uuids' => [$booking->items[2]->concert->uuid],
        ]);
        $url = route('customer.studio-communications.index', $studio);
        // An available password or disabled approval requirement must not reveal the code.
        $first->update(['requires_approval' => false]);
        $this->actingAs($customer)->get($url)->assertOk()->assertSee('PHOTO-HUB-TEST')
            ->assertDontSee('VIDEO-HUB-TEST')->assertDontSee('GROUP-HUB-TEST');
        $first->update(['approved_at' => now(), 'approved_by_user_id' => $customer->id]);
        $this->get($url)->assertOk()->assertSee('VIDEO-HUB-TEST')->assertDontSee('GROUP-HUB-TEST');
        $booking->items[2]->concert->update(['approved_at' => now(), 'approved_by_user_id' => $customer->id]);
        $hub = app(StudioCommunicationHistory::class)->forUser($customer, $studio->fresh())['hub']['events'];
        foreach ($hub as $card) {
            $this->assertSame('PHOTO-HUB-TEST', $card['production']->firstWhere('label', 'Photo gallery released')['loginCode']['value']);
        }
        $portrait = $hub->firstWhere('event.id', $booking->items[0]->scheduling_event_id);
        $this->assertNull($portrait['production']->firstWhere('label', 'Review & release concert video'));
        $this->assertSame('VIDEO-HUB-TEST', $hub->firstWhere('event.id', $booking->items[1]->scheduling_event_id)['production']->firstWhere('label', 'Review & release concert video')['loginCode']['value']);
        $this->assertSame('GROUP-HUB-TEST', $hub->firstWhere('event.id', $booking->items[2]->scheduling_event_id)['production']->firstWhere('label', 'Review & release concert video')['loginCode']['value']);
        $url = route('customer.studio-communications.index', $studio);
        $this->actingAs($customer)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Photo gallery login code')->assertSee('Concert video login code')->assertSee('PHOTO-HUB-TEST')->assertSee('VIDEO-HUB-TEST')->assertSee('GROUP-HUB-TEST');
        $first->update(['approved_at' => null, 'approved_by_user_id' => null]);
        $this->get($url)->assertOk()->assertDontSee('VIDEO-HUB-TEST')->assertSee('GROUP-HUB-TEST');
        $outsider = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $outsider->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->actingAs($outsider)->get($url)->assertForbidden()->assertDontSee('PHOTO-HUB-TEST');
        StudioAccountAccess::where('user_id', $customer->id)->update(['status' => 'revoked']);
        $this->actingAs($customer)->get($url)->assertForbidden()->assertDontSee('VIDEO-HUB-TEST');
    }

    public function test_hub_handles_missing_legacy_and_changed_codes_without_showing_old_values(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $url = route('customer.studio-communications.index', $studio);
        $this->actingAs($customer)->get($url)->assertOk()->assertDontSee('Photo gallery login code:')->assertDontSee('Concert video login code:');
        $studio->forceFill(['gallery_code_hash' => Hash::make('LEGACY-HASH-ONLY')])->save();
        $this->get($url)->assertOk()->assertDontSee('Photo gallery login code:')->assertDontSee('LEGACY-HASH-ONLY');
        $studio->forceFill(['gallery_code_encrypted' => 'OUTDATED-CODE'])->save();
        $this->get($url)->assertOk()->assertDontSee('OUTDATED-CODE');
        app(SaveStudioGalleryCode::class)->execute($studio->fresh(), $staff, 'REPLACEMENT-CODE', 0);
        $this->get($url)->assertOk()->assertSee('REPLACEMENT-CODE')->assertDontSee('OUTDATED-CODE');
    }

    private function fixture(): array
    {
        $studio = Studio::factory()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $customer->id, 'email' => $customer->email, 'status' => 'active', 'role' => 'director']);
        $venue = Venue::create(['name' => 'Rehearsal theatre', 'address_line_1' => '1 Example Road']);
        $data = ['studio_name' => $studio->name, 'contact_name' => 'Test director', 'contact_email' => $customer->email, 'contact_phone' => '0400000000',
            'wants_portrait_photography' => true, 'wants_concert_photography' => true, 'wants_concert_videography' => true,
            'approximate_family_count' => 80, 'accepted_requirements' => ['accurate'], 'items' => []];
        foreach (['dress_rehearsal', 'concert', 'concert'] as $index => $type) {
            $data['items'][] = ['item_type' => $type, 'title' => 'Rehearsal event '.($index + 1), 'venue_uuid' => $venue->uuid,
                'event_date' => '2026-10-'.(20 + $index), 'starts_at' => '18:00', 'finishes_at' => '20:00'];
        }
        $booking = app(CreateConcertBooking::class)->execute($data, $studio);

        return [$booking, $studio, $staff, $customer];
    }
}
