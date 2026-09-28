<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingEventMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_approval_controls_open_the_confirmation_without_sending_it(): void
    {
        foreach (['button', 'tracker'] as $source) {
            [$staff, $booking] = $this->fixture();
            $this->actingAs($staff);
            $response = $source === 'button'
                ? $this->post(route('admin.concert-bookings.approve', $booking))
                : $this->patchJson(route('admin.concert-bookings.tracker.update', $booking), ['panel' => 'status', 'status_override' => 'approved']);
            $event = $booking->items->first()->fresh()->schedulingEvent;
            $this->assertNotNull($event);
            $url = route('admin.concert-bookings.confirmation', $booking);
            $source === 'button' ? $response->assertRedirect($url) : $response->assertOk()->assertJsonPath('data.redirect', $url);
            $this->assertSame('approved', $booking->fresh()->status->value);
            $this->assertNull(ConcertTrackerTask::where('scope_id', $booking->id)->where('task_key', 'booking_received')->firstOrFail()->status_override);
            $this->get($url)->assertOk()->assertSee('Booking confirmation')->assertSee('Back to booking');
            $this->get(route('admin.concert-tracker.tasks.show', [$event, 'confirmation_information_email']))->assertRedirect($url);
            $catalog = app(ConcertTrackerTaskCatalog::class);
            $this->assertFalse($catalog->all()->contains('key', 'confirmation_information_email'));
            $this->assertSame('1.2', $catalog->find('final_numbers_email')['number']);
            $this->assertDatabaseCount('concert_tracker_email_deliveries', 0);
            Notification::assertNothingSent();
        }
    }

    public function test_merged_confirmation_sends_and_retains_delivery_history_under_booking_workflow(): void
    {
        [$staff, $booking] = $this->fixture();
        Mail::fake();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $page = route('admin.concert-bookings.confirmation', $booking);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('Send booking confirmation')->assertSee('Confirmation: Not sent');
        $this->from($page)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'confirmation_information_email']), [
            'recipient_name' => 'Director', 'recipient_email' => 'director@example.test',
        ])->assertRedirect($page)->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        Mail::assertSent(ConcertTrackerMessage::class, 1);
        $this->get($page)->assertOk()->assertSee('Delivery history')->assertSee('director@example.test');
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('Confirmation:')->assertSee('Sent');
    }

    public function test_tracker_approval_obeys_venue_validation_and_repeated_approval_does_not_duplicate_events(): void
    {
        [$staff, $booking] = $this->fixture();
        $item = $booking->items->first();
        $venueId = $item->venue_id;
        $item->update(['venue_id' => null]);
        $url = route('admin.concert-bookings.tracker.update', $booking);
        $this->actingAs($staff)->patchJson($url, ['panel' => 'status', 'status_override' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('venue');
        $this->assertDatabaseCount('scheduling_events', 0);
        $item->update(['venue_id' => $venueId]);
        $this->patchJson($url, ['panel' => 'status', 'status_override' => 'approved'])->assertOk();
        $this->patchJson($url, ['panel' => 'status', 'status_override' => 'approved'])->assertOk();
        $this->assertDatabaseCount('scheduling_events', 1);
    }

    public function test_seats_and_files_are_shared_with_event_records_and_replacements_retain_history(): void
    {
        [$staff, $booking] = $this->fixture();
        $item = $booking->items->first();
        $event = app(ApproveConcertBooking::class)->approveItem($item, $staff);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']))->assertOk()->assertSee('1.3 Seats &amp; families numbers', false)->assertSee('booking-follow-on')->assertSee('The value below starts with the booking estimate.');
        $this->assertNull($booking->fresh()->family_count_confirmed_at);
        $this->post(route('admin.concert-booking-events.seats', $item), ['roles' => [
            'concert-photographer-p1' => ['seating' => 'Row A: 1, 2'],
            'concert-videographer' => ['seating' => 'Row B: 1, 2, 3, 4, 5'],
        ]])->assertSessionHasNoErrors();
        $this->assertCount(2, $event->roleSeats()->get());
        $url = route('admin.concert-booking-events.files', $item);
        $this->post($url, ['kind' => 'studio_video', 'files' => [UploadedFile::fake()->create('intro.pptx', 10)]])->assertSessionHasNoErrors();
        $original = StudioEventFile::firstOrFail();
        $this->patch(route('admin.concert-tracker.studio-event-files.review', $original), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('complete', app(ConcertTracker::class)->detail($event, 'slideshow_received')['status']['key']);
        $this->post($url, ['kind' => 'studio_video', 'replace_uuid' => $original->uuid, 'files' => [UploadedFile::fake()->create('converted.mp4', 10)]])->assertSessionHasNoErrors();
        $this->assertFalse($original->fresh()->is_current);
        $this->assertSame('superseded', $original->fresh()->status);
        Storage::disk('local')->assertExists($original->storage_key);
        $replacement = $event->studioFiles()->where('is_current', true)->sole();
        $this->assertSame('received', $replacement->status);
        $this->assertSame($staff->id, $replacement->uploaded_by_user_id);
        $this->assertSame('in_progress', app(ConcertTracker::class)->detail($event, 'slideshow_received')['status']['key']);
        $this->post($url, ['kind' => 'programme', 'files' => [UploadedFile::fake()->create('programme.docx', 10)]])->assertSessionHasNoErrors();
        $programme = $event->studioFiles()->where('kind', 'programme')->sole();
        $this->assertSame('needs_conversion', $programme->status);
        $this->assertSame($programme->storage_key, $item->fresh()->programme_path);
        $this->assertSame($programme->storage_key, $event->fresh()->programme_path);
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('converted.mp4');
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']))->assertOk()->assertSee('Row A: 1, 2');
        $this->post($url, ['kind' => 'programme', 'files' => [UploadedFile::fake()->createWithContent('converted.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")]])->assertSessionHasNoErrors();
        $this->assertFalse($programme->fresh()->is_current);
        $currentProgramme = $event->studioFiles()->where('kind', 'programme')->where('is_current', true)->sole();
        $this->assertSame('received', $currentProgramme->status);
        $this->assertDatabaseHas('programmes', ['scheduling_event_id' => $event->id]);
        Storage::disk('local')->assertExists($programme->storage_key);

    }

    public function test_materials_reject_customer_access_cross_event_replacement_and_pending_writes(): void
    {
        [$staff, $booking] = $this->fixture();
        $item = $booking->items->first();
        $url = route('admin.concert-booking-events.files', $item);
        $payload = fn () => ['kind' => 'studio_video', 'files' => [UploadedFile::fake()->create('intro.mp4', 10)]];
        $this->actingAs(User::factory()->customer()->create())->post($url, $payload())->assertForbidden();
        $this->actingAs($staff)->post($url, $payload())->assertSessionHasErrors('event');
        app(ApproveConcertBooking::class)->approveItem($item, $staff);
        $this->post($url, $payload())->assertSessionHasNoErrors();
        $file = StudioEventFile::firstOrFail();
        [, $other] = $this->fixture();
        app(ApproveConcertBooking::class)->approveItem($other->items->first(), $staff);
        $this->post(route('admin.concert-booking-events.files', $other->items->first()), $payload() + ['replace_uuid' => $file->uuid])->assertSessionHasErrors('replace_uuid');
        $this->assertTrue($file->fresh()->is_current);
        $this->assertDatabaseCount('studio_event_files', 1);
    }

    public function test_seats_families_email_has_tracker_bar_and_manual_approval_then_send_completes_it(): void
    {
        [$staff, $booking] = $this->fixture();
        Mail::fake();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $page = route('admin.concert-tracker.tasks.show', [$event, 'final_numbers_email']);
        $update = route('admin.concert-tracker.seats-families-email.update', $event);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('1.2 Seats &amp; families email', false)->assertSee('Tracker task controls')->assertSee('Email action')->assertDontSee('task-layout');
        $this->patchJson($update, ['panel' => 'status', 'status_override' => 'approved'])->assertOk()->assertSee('Approved');
        $this->assertSame('Approved', app(ConcertTracker::class)->detail($event, 'final_numbers_email')['status']['label']);
        $this->assertDatabaseCount('concert_tracker_email_deliveries', 0);
        $this->patchJson($update, ['panel' => 'follow_up', 'follow_up_status' => 'open', 'follow_up_date' => '2026-10-01', 'note' => 'Director supplied details by phone.'])->assertOk();
        $task = ConcertTrackerTask::where('task_key', 'final_numbers_email')->sole();
        $this->assertSame('complete', $task->status_override);
        $this->assertSame(1, $task->notes()->count());
        $this->from($page)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'final_numbers_email']), ['recipient_name' => 'Director', 'recipient_email' => 'director@example.test'])->assertSessionHasNoErrors();
        (new SendConcertTrackerEmail(ConcertTrackerEmailDelivery::sole()->id))->handle();
        $this->assertNull($task->fresh()->status_override);
        $status = app(ConcertTracker::class)->detail($event, 'final_numbers_email')['status'];
        $this->assertSame('Completed', $status['label']);
        $this->assertStringContainsString($staff->name, $status['activity']);
        Mail::assertSent(ConcertTrackerMessage::class, 1);
        $this->actingAs(User::factory()->customer()->create())->patchJson($update, ['panel' => 'status', 'status_override' => 'approved'])->assertForbidden();
    }

    public function test_email_pages_require_deliberate_resends_and_block_duplicate_submissions(): void
    {
        [$staff, $booking] = $this->fixture();
        Mail::fake();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $secondItem = $booking->items->first()->replicate(['uuid', 'scheduling_event_id']);
        $secondItem->title = 'Second concert';
        $secondItem->save();
        $second = app(ApproveConcertBooking::class)->approveItem($secondItem, $staff);
        $this->actingAs($staff);
        foreach (['final_numbers_email', 'concert_week_email', 'post_concert_email'] as $key) {
            $page = route('admin.concert-tracker.tasks.show', [$event, $key]);
            $send = route('admin.concert-tracker.tasks.email.send', [$event, $key]);
            $update = route('admin.concert-tracker.email-bar.update', [$event, $key]);
            $input = ['recipient_email' => 'director@example.test'];
            $this->get($page)->assertOk()->assertSee('Tracker task controls')->assertSee('Email action')->assertDontSee('task-layout');
            $this->patchJson($update, ['panel' => 'status', 'status_override' => 'approved'])->assertOk()->assertSee('Approved');
            $task = app(ConcertTracker::class)->detail($event, $key)['task'];
            $this->assertSame($key === 'final_numbers_email' ? 'booking' : 'event', $task->scope_type);
            if ($key !== 'final_numbers_email') {
                $this->assertSame('not_started', app(ConcertTracker::class)->detail($second, $key)['status']['key']);
            }
            $this->from($page)->post($send, $input)->assertSessionHasNoErrors();
            $delivery = $task->deliveries()->sole();
            $this->post($send, $input)->assertSessionHasErrors('email');
            $this->assertSame(1, $task->deliveries()->count());
            $this->get($page)->assertOk()->assertSee('Email queued')->assertDontSee('>Send email</button>', false);
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->assertSame('Completed', app(ConcertTracker::class)->detail($event, $key)['status']['label']);
            $this->get($page)->assertOk()->assertSee('Test email captured')->assertSee('director@example.test')->assertSee($staff->name)->assertSee('Send another copy')->assertSee('name="confirm_resend"', false);
            $this->post($send, $input)->assertSessionHasErrors('email');
            $this->post(route('admin.concert-tracker.deliveries.resend', $delivery))->assertSessionHasErrors('delivery');
            $resend = $input + ['confirm_resend' => true, 'previous_delivery_id' => $delivery->id];
            $this->post($send, $resend)->assertSessionHasNoErrors();
            $this->post($send, $resend)->assertSessionHasErrors('email');
            $latest = $task->deliveries()->reorder()->orderByDesc('id')->firstOrFail();
            (new SendConcertTrackerEmail($latest->id))->handle();
            $this->post($send, $resend)->assertSessionHasErrors('email');
            $this->assertSame(2, $task->deliveries()->count());
            $this->actingAs(User::factory()->customer()->create())->patchJson($update, ['panel' => 'status', 'status_override' => 'approved'])->assertForbidden();
            $this->actingAs($staff);
        }
        Mail::assertSent(ConcertTrackerMessage::class, 6);
    }

    public function test_numbers_need_confirmed_families_and_seats_for_every_concert_but_not_portraits(): void
    {
        [$staff, $booking] = $this->fixture();
        $booking->update(['wants_portrait_photography' => true]);
        $original = $booking->items->first();
        $second = $original->replicate(['uuid', 'scheduling_event_id']);
        $second->title = 'Senior concert';
        $second->save();
        $portrait = $original->replicate(['uuid', 'scheduling_event_id']);
        $portrait->item_type = 'dress_rehearsal';
        $portrait->title = 'Portraits';
        $portrait->save();
        $events = collect([$original, $second, $portrait])->map(fn ($item) => app(ApproveConcertBooking::class)->approveItem($item, $staff));
        $status = fn ($event) => app(ConcertTracker::class)->detail($event->fresh(), 'final_numbers_received')['status'];
        $this->assertSame('80 families (quoted)', $status($events[0])['label']);
        $this->assertSame('not_required', $status($events[2])['key']);
        $anchor = route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']);
        $this->actingAs($staff)->get(route('admin.concert-tracker.tasks.show', [$events[0], 'final_numbers_received']))->assertRedirect($anchor);
        $this->post(route('admin.concert-bookings.families', $booking), ['final_family_count' => 95, 'confirm_unique_families' => '1'])->assertRedirect($anchor)->assertSessionHasNoErrors();
        $this->assertSame('waiting', $status($events[0])['key']);
        $roles = ['concert-photographer-p1' => ['seating' => 'Row N: 23'], 'concert-videographer' => ['seating' => 'Row M: 1, 2, 3, 4, 5']];
        $this->post(route('admin.concert-booking-events.seats', $original), ['roles' => $roles])->assertSessionHasNoErrors();
        $this->assertSame('waiting', $status($events[0])['key']);
        $this->post(route('admin.concert-booking-events.seats', $second), ['roles' => $roles])->assertRedirect($anchor)->assertSessionHasNoErrors();
        $complete = $status($events[0]);
        $this->assertSame('complete', $complete['key']);
        $this->assertSame('95 families', $complete['label']);
        $this->assertContains('P1: N23', $complete['seatSummary']);
        $this->assertStringContainsString($staff->name, $complete['activity']);
        $this->assertSame('not_required', $status($events[2])['key']);
        $this->assertSame($anchor, $complete['detailUrl']);
        $this->get($anchor)->assertOk()->assertSee('Total final families')->assertSee('Row N: 23');
        $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'view' => 'studio', 'phase' => 'booking']))->assertOk()->assertSee('P1: N23')->assertSee('95 families');
    }

    public function test_booking_task_tabs_have_separate_bars_and_independent_numbers_controls(): void
    {
        [$staff, $booking] = $this->fixture();
        app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $bookingPage = route('admin.concert-bookings.show', $booking);
        $numbersPage = route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']);
        $this->actingAs($staff)->get($bookingPage)->assertOk()->assertSee('Back to tracker')->assertSee('booking-task-tabs')->assertDontSee('Event availability')->assertDontSee('Pending events')->assertSee('booking-edit-form')->assertDontSee('name="final_family_count"', false);
        $this->get($numbersPage)->assertOk()->assertSee('Tracker task controls')->assertSee('80 families (quoted)')->assertSee('name="final_family_count"', false)->assertDontSee('name="studio_name"', false)->assertDontSee('Save booking changes');
        $update = route('admin.concert-bookings.numbers-tracker.update', $booking);
        $this->patchJson($update, ['panel' => 'assignment', 'assignee_user_id' => $staff->id])->assertOk();
        $this->patchJson($update, ['panel' => 'due', 'due_date_override' => '2026-10-15'])->assertOk();
        $this->patchJson($update, ['panel' => 'follow_up', 'follow_up_status' => 'waiting', 'follow_up_date' => '2026-10-10', 'note' => 'Waiting for reserved seats.'])->assertOk();
        $task = ConcertTrackerTask::where('task_key', 'final_numbers_received')->sole();
        $this->assertSame($staff->id, $task->assignee_user_id);
        $this->assertSame('2026-10-15', $task->due_date_override->toDateString());
        $this->assertSame('waiting', $task->follow_up_status);
        $this->assertSame(1, $task->notes()->count());
        $this->patchJson($update, ['panel' => 'status', 'status_override' => 'approved'])->assertUnprocessable();
        $this->assertSame('approved', $booking->fresh()->status->value);
        $this->assertNull($booking->fresh()->family_count_confirmed_at);
        $this->actingAs(User::factory()->customer()->create())->patchJson($update, ['panel' => 'due', 'due_date_override' => '2026-10-15'])->assertForbidden();
    }

    public function test_seats_and_families_are_blue_until_due_then_yellow_and_overdue_red(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        [$staff, $booking] = $this->fixture();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $status = fn () => app(ConcertTracker::class)->detail($event->fresh(), 'final_numbers_received')['status'];
        $this->assertSame('in_progress', $status()['tone']);
        $this->assertSame('Seats not confirmed', $status()['seatsLabel']);
        $this->actingAs($staff)->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'booking']))->assertOk()->assertSee('badge status-in_progress">80 families (quoted)', false)->assertSee('badge status-in_progress">Seats not confirmed', false);
        $task = ConcertTrackerTask::where('task_key', 'final_numbers_received')->sole();
        $task->update(['due_date_override' => '2026-10-09']);
        $this->assertSame('in_progress', $status()['tone']);
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
        $this->assertSame('in_progress', $status()['tone']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']))->assertOk()->assertSee('tracker-bar-status ready', false);
        $this->travelTo(Carbon::parse('2026-10-06 00:01:00'));
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']))->assertOk()->assertSee('tracker-bar-status near-due', false);
        $this->assertSame('near-due', $status()['tone']);
        $this->travelTo(Carbon::parse('2026-10-09 23:00:00'));
        $this->assertSame('near-due', $status()['tone']);
        $this->travelTo(Carbon::parse('2026-10-10 00:01:00'));
        $this->assertSame('overdue', $status()['tone']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']))->assertOk()->assertSee('tracker-bar-status overdue', false);
    }

    public function test_actual_email_send_sets_ten_day_deadline_and_preserves_other_task_fields(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));
        [$staff, $booking] = $this->fixture();
        Mail::fake();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $task = app(ConcertTracker::class)->detail($event, 'final_numbers_received')['task'];
        $task->update(['assignee_user_id' => $staff->id, 'follow_up_status' => 'waiting', 'follow_up_date' => '2026-10-15']);
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'final_numbers_email']), [
            'recipient_name' => 'Director', 'recipient_email' => 'director@example.test', 'scheduled_at' => '2026-10-03T12:00',
        ])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::sole();
        $job = new SendConcertTrackerEmail($delivery->id);
        $job->handle();
        $this->assertNull($task->fresh()->due_date_override);
        $this->travelTo(Carbon::parse('2026-10-04 13:00:00'));
        $job->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('2026-10-14', $task->fresh()->due_date_override->toDateString());
        $this->assertSame($staff->id, $task->fresh()->assignee_user_id);
        $this->assertSame('waiting', $task->fresh()->follow_up_status);
        $this->assertSame('2026-10-15', $task->fresh()->follow_up_date->toDateString());
        $this->assertSame('in_progress', app(ConcertTracker::class)->detail($event, 'final_numbers_received')['status']['tone']);
        $this->assertDatabaseHas('concert_tracker_task_changes', ['concert_tracker_task_id' => $task->id, 'action' => 'email_deadline_set', 'user_id' => $staff->id]);
        $this->travelTo(Carbon::parse('2026-10-05 13:00:00'));
        $job->handle();
        $this->assertSame('2026-10-14', $task->fresh()->due_date_override->toDateString());
        Mail::assertSent(ConcertTrackerMessage::class, 1);
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'final_numbers_email']), [
            'confirm_resend' => true, 'previous_delivery_id' => $delivery->id,
            'recipient_name' => 'Director', 'recipient_email' => 'director@example.test',
        ])->assertSessionHasNoErrors();
        (new SendConcertTrackerEmail(ConcertTrackerEmailDelivery::latest('id')->firstOrFail()->id))->handle();
        $this->assertSame('2026-10-15', $task->fresh()->due_date_override->toDateString());
    }

    public function test_failed_email_and_manual_approval_do_not_set_numbers_deadline(): void
    {
        [$staff, $booking] = $this->fixture();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $task = app(ConcertTracker::class)->detail($event, 'final_numbers_received')['task'];
        $this->actingAs($staff)->patchJson(route('admin.concert-tracker.seats-families-email.update', $event), ['panel' => 'status', 'status_override' => 'approved'])->assertOk();
        $this->assertNull($task->fresh()->due_date_override);
        $this->post(route('admin.concert-tracker.tasks.email.send', [$event, 'final_numbers_email']), [
            'recipient_name' => 'Director', 'recipient_email' => 'director@example.test',
        ])->assertSessionHasNoErrors();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Test mail failure'));
        $delivery = ConcertTrackerEmailDelivery::sole();
        try {
            (new SendConcertTrackerEmail($delivery->id))->handle();
            $this->fail('Expected mail failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test mail failure', $exception->getMessage());
        }
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNull($task->fresh()->due_date_override);
    }

    public function test_confirming_and_correcting_families_updates_green_count_while_seats_stay_open(): void
    {
        [$staff, $booking] = $this->fixture();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $this->actingAs($staff);
        foreach ([95, 110] as $count) {
            $this->post(route('admin.concert-bookings.families', $booking), ['final_family_count' => $count, 'confirm_unique_families' => '1'])->assertSessionHasNoErrors();
            $status = app(ConcertTracker::class)->detail($event->fresh(), 'final_numbers_received')['status'];
            $this->assertSame($count.' families', $status['label']);
            $this->assertTrue($status['familiesConfirmed']);
            $this->assertSame('waiting', $status['key']);
            $this->assertSame('Seats not confirmed', $status['seatsLabel']);
            $this->get(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'booking']))->assertOk()->assertSee('badge success">'.$count.' families', false)->assertSee('badge status-in_progress">Seats not confirmed', false)->assertDontSee('80 families (quoted)');
        }
        $this->assertSame(80, (int) $booking->fresh()->approximate_family_count);
    }

    private function fixture(): array
    {
        Queue::fake();
        Notification::fake();
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $booking = ConcertBooking::create(['status' => 'pending', 'studio_id' => $studio->id, 'studio_name' => $studio->name, 'contact_name' => 'Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000', 'wants_concert_photography' => true, 'wants_concert_videography' => true, 'approximate_family_count' => 80]);
        $venue = Venue::create(['name' => 'Theatre', 'address_line_1' => 'Example Road']);
        $booking->items()->create(['item_type' => 'concert', 'title' => 'Junior concert', 'event_date' => '2026-10-20', 'venue_id' => $venue->id, 'venue_name' => $venue->name, 'venue_address' => 'Example Road', 'starts_at' => '18:00', 'finishes_at' => '20:00']);

        return [$staff, $booking->fresh('items')];
    }
}
