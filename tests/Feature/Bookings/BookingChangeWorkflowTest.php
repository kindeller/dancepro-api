<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Actions\CancelConcertBookingItem;
use App\Features\Bookings\Actions\ConfirmBookingFamilies;
use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Actions\UpdateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\CurrentBookingTimeline;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Studios\Actions\CreateStudioBookingChange;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerAutomationRule;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Tracking\Services\ConcertTrackerAutomation;
use App\Features\Tracking\Services\ConcertTrackerEmails;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingChangeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_a_request_updates_all_linked_records_and_retains_original_submission(): void
    {
        Queue::fake();
        [$booking, , $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.schedulingEvent.shifts', 'items.venue');
        $item = $booking->items[1];
        $event = $item->schedulingEvent;
        $shift = $event->shifts->first();
        $shift->update(['posted_arrival_at' => $shift->starts_at->copy()->subHours(2)]);
        $snapshot = $booking->submission_snapshot;
        $change = app(CreateStudioBookingChange::class)->execute($event, $customer, [
            'booking' => ['starts_at' => '19:00'], 'details' => 'Later curtain-up',
        ]);
        $delivery = $this->scheduleEmail($event, $staff);
        $payload = $this->payload($booking);
        $payload['items'][1]['starts_at'] = '19:00';
        $payload['items'][1]['title'] = 'Updated concert title';
        $payload['booking_change_uuid'] = $change->uuid;
        $payload['resolution_note'] = 'Agreed with the director: curtain up at 7 pm.';
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', [$booking, 'change_request' => $change->uuid]))
            ->assertOk()->assertSee('Later curtain-up')->assertSee('Agreed resolution');
        $this->put(route('admin.concert-bookings.update', $booking), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $change->fresh()->status);
        $this->assertSame(['starts_at' => '19:00'], $change->requested_fields);
        $this->assertSame('17:00', $shift->fresh()->posted_arrival_at->format('H:i'));
        $this->assertSame('19:00', $shift->fresh()->starts_at->format('H:i'));
        $this->assertSame('Updated concert title', $item->fresh()->concert->name);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->invalidated_at);
        $revision = $booking->revisions()->sole();
        $this->assertSame($staff->id, $revision->user_id);
        $this->assertSame($change->id, $revision->studio_booking_change_request_id);
        $this->assertSame($snapshot, $booking->fresh()->submission_snapshot);
        $this->assertDatabaseMissing('concert_tracker_alerts', ['type' => 'studio_booking_change', 'resolved_at' => null]);
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('Booking change history')->assertSee('Updated concert title');
    }

    public function test_request_cannot_be_completed_without_changes_or_against_another_booking(): void
    {
        [$booking, , $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.schedulingEvent', 'items.venue');
        $change = app(CreateStudioBookingChange::class)->execute($booking->items[0]->schedulingEvent, $customer, ['booking' => ['starts_at' => '19:00']]);
        $payload = $this->payload($booking) + ['booking_change_uuid' => $change->uuid, 'resolution_note' => 'No actual edits'];
        $this->actingAs($staff)->put(route('admin.concert-bookings.update', $booking), $payload)->assertSessionHasErrors('booking_change_uuid');
        $this->assertSame('requested', $change->fresh()->status);
        $this->assertDatabaseCount('concert_booking_revisions', 0);
        [$other] = $this->fixture();
        $this->put(route('admin.concert-bookings.update', $other), $this->payload($other) + ['booking_change_uuid' => $change->uuid, 'resolution_note' => 'Wrong booking'])
            ->assertSessionHasErrors('booking_change_uuid');
        $this->get(route('admin.concert-bookings.show', [$other, 'change_request' => $change->uuid]))->assertNotFound();
        $this->actingAs($customer)->put(route('admin.concert-bookings.update', $booking), $payload)->assertForbidden();
    }

    public function test_stale_delivery_is_blocked_at_send_time_and_cannot_be_resent(): void
    {
        Queue::fake();
        Mail::fake();
        [$booking, , $staff] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $event = $booking->fresh('items.schedulingEvent')->items[1]->schedulingEvent;
        $delivery = $this->scheduleEmail($event, $staff);
        // Also guard edits made outside the booking editor.
        $booking->update(['contact_email' => 'new-contact@example.test']);
        $delivery->update(['scheduled_at' => now()->subMinute()]);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->invalidated_at);
        Mail::assertNothingSent();
        $this->expectException(ValidationException::class);
        app(ConcertTrackerEmails::class)->resend($delivery->fresh(), $staff);
    }

    public function test_invalidated_reminders_require_manual_review_then_use_current_details(): void
    {
        Queue::fake();
        [$booking, , $staff] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.schedulingEvent', 'items.venue');
        $event = $booking->items[1]->schedulingEvent;
        $delivery = $this->scheduleEmail($event, $staff);
        ConcertTrackerAutomationRule::query()->update(['mode' => 'disabled']);
        ConcertTrackerAutomationRule::where('task_key', 'concert_week_email')->update(['mode' => 'automatic']);
        $payload = $this->payload($booking);
        $payload['contact_email'] = 'corrected@example.test';
        app(UpdateConcertBooking::class)->execute($booking, $payload, $staff);
        $automation = app(ConcertTrackerAutomation::class);
        $candidate = $automation->candidatesForYear(2026)->first(fn ($row) => $row['task']->id === $delivery->concert_tracker_task_id);
        $this->assertTrue($candidate['needs_review']);
        $automation->scheduleAutomatic();
        $this->assertSame(1, ConcertTrackerEmailDelivery::where('concert_tracker_task_id', $delivery->concert_tracker_task_id)->count());
        $fresh = $automation->schedule($event->fresh(), 'concert_week_email', $staff);
        $this->assertSame('corrected@example.test', $fresh->recipient_email);
        $this->assertNotSame($delivery->context_hash, $fresh->context_hash);
        $this->assertSame('scheduled', $fresh->status);
        $this->actingAs($staff)->get(route('admin.concert-tracker.automation.index', ['year' => 2026]))->assertOk();
    }

    public function test_cancellation_invalidates_booking_reminders_and_excludes_cancelled_event_from_new_mail(): void
    {
        Queue::fake();
        [$booking, , $staff] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.schedulingEvent');
        $event = $booking->items[1]->schedulingEvent;
        $delivery = $this->scheduleEmail($event, $staff);
        app(ConfirmBookingFamilies::class)->execute($booking, 250, $staff);
        app(CancelConcertBookingItem::class)->execute($booking->items[2], $staff);
        $this->assertNull($booking->fresh()->final_family_count);
        $this->assertNull($booking->fresh()->family_count_confirmed_at);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $template = ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail();
        $rendered = app(ConcertTracker::class)->renderTemplate($template->subject, '{{ event_details }}', $event->fresh());
        $this->assertStringContainsString('Rehearsal event 2', $rendered['body']);
        $this->assertStringNotContainsString('Rehearsal event 3', $rendered['body']);
        $this->expectException(ValidationException::class);
        app(CreateStudioBookingChange::class)->execute($booking->items[2]->schedulingEvent->fresh(), $staff, ['booking' => ['starts_at' => '19:00']]);
    }

    public function test_final_family_confirmation_updates_quote_and_tracker_without_overwriting_original_estimate(): void
    {
        Queue::fake();
        [$booking, $studio, $staff, $customer] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $this->actingAs($customer)->post(route('customer.studio-bookings.families', [$studio, $booking]), [
            'final_family_count' => 250, 'confirm_unique_families' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(250, $booking->final_family_count);
        $this->assertSame($customer->id, $booking->family_count_confirmed_by);
        $this->assertSame(80, (int) $booking->approximate_family_count);
        $this->assertSame(80, $booking->submission_snapshot['quote']['families']);
        $timeline = app(CurrentBookingTimeline::class)->forBooking($booking, $studio);
        $this->assertSame(375000, $timeline['quote']['video_quote_cents']);
        $this->assertTrue($timeline['quote']['families_confirmed']);
        $event = $booking->items[1]->schedulingEvent;
        $task = app(ConcertTracker::class)->detail($event, 'final_numbers_received')['status'];
        $this->assertSame('waiting', $task['key']);
        $this->assertSame('250 families', $task['label']);
        $this->assertTrue($task['familiesConfirmed']);
        $this->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertOk()->assertSee('250 confirmed families')->assertSee('3,750.00');
        $this->post(route('customer.studio-bookings.families', [$studio, $booking]), [
            'final_family_count' => 250, 'confirm_unique_families' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $booking->revisions()->count());
        $this->actingAs($staff)->post(route('admin.concert-bookings.families', $booking), [
            'final_family_count' => 190, 'confirm_unique_families' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(300000, app(CurrentBookingTimeline::class)->forBooking($booking->fresh(), $studio)['quote']['video_quote_cents']);
        $this->assertSame(2, $booking->revisions()->count());
    }

    public function test_final_family_confirmation_requires_access_confirmation_and_active_concert_booking(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        $url = route('customer.studio-bookings.families', [$studio, $booking]);
        $data = ['final_family_count' => 100, 'confirm_unique_families' => '1'];
        $stranger = User::factory()->customer()->create();
        $this->actingAs($stranger)->post($url, $data)->assertForbidden();
        $this->actingAs($customer)->post($url, ['final_family_count' => 100])->assertSessionHasErrors('confirm_unique_families');
        $this->post($url, ['final_family_count' => 0, 'confirm_unique_families' => '1'])->assertSessionHasErrors('final_family_count');
        [$other] = $this->fixture();
        $this->post(route('customer.studio-bookings.families', [$studio, $other]), $data)->assertNotFound();
        $booking->update(['wants_concert_videography' => false]);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $booking->update(['wants_concert_videography' => true, 'cancelled_at' => now()]);
        $this->post($url, $data)->assertSessionHasErrors('final_family_count');
        $this->assertSame(100, $booking->fresh()->final_family_count);
    }

    public function test_service_changes_update_default_setup_and_requirements_and_overnight_times(): void
    {
        [$booking, , $staff] = $this->fixture();
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->load('items.schedulingEvent.shifts', 'items.venue');
        $payload = $this->payload($booking);
        $payload['wants_concert_videography'] = false;
        $payload['items'][1]['starts_at'] = '23:00';
        $payload['items'][1]['finishes_at'] = '01:00';
        app(UpdateConcertBooking::class)->execute($booking, $payload, $staff);
        $event = $booking->items[1]->schedulingEvent->fresh('shifts', 'roleRequirements.crewRole');
        $shift = $event->shifts->first();
        $this->assertSame('22:00', $shift->posted_arrival_at->format('H:i'));
        $this->assertSame('2026-10-22 01:20', $shift->estimated_finish_at->format('Y-m-d H:i'));
        $this->assertFalse($event->roleRequirements->contains(fn ($row) => $row->crewRole->code === 'concert-videographer'));
        $this->assertTrue($event->roleRequirements->contains(fn ($row) => $row->crewRole->code === 'concert-photographer-p1'));
    }

    private function scheduleEmail($event, User $staff): ConcertTrackerEmailDelivery
    {
        $task = app(ConcertTracker::class)->detail($event, 'concert_week_email')['task'];

        return app(ConcertTrackerEmails::class)->queue($event, $task, ConcertTrackerEmailTemplate::where('task_key', 'concert_week_email')->firstOrFail(), $staff,
            ['recipient_email' => 'director@example.test', 'scheduled_at' => now()->addDay()->toDateTimeString()]);
    }

    private function payload(ConcertBooking $booking): array
    {
        return $booking->only(['studio_name', 'contact_name', 'contact_email', 'contact_phone', 'wants_portrait_photography', 'wants_concert_photography', 'wants_concert_videography', 'approximate_family_count']) + [
            'items' => $booking->items->map(fn ($item) => [
                'uuid' => $item->uuid, 'item_type' => $item->item_type->value, 'event_type_definition_id' => $item->event_type_definition_id,
                'title' => $item->title, 'venue_uuid' => $item->venue->uuid, 'event_date' => $item->event_date->toDateString(),
                'starts_at' => substr($item->starts_at, 0, 5), 'finishes_at' => substr($item->finishes_at, 0, 5),
            ])->all(),
        ];
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
            $definition = EventTypeDefinition::firstOrCreate(['name' => 'Test '.$type], ['code' => 'test-'.$type, 'system_category' => 'concert', 'is_active' => true]);
            $data['items'][] = ['item_type' => $type, 'event_type_definition_id' => $definition->id, 'title' => 'Rehearsal event '.($index + 1), 'venue_uuid' => $venue->uuid,
                'event_date' => '2026-10-'.(20 + $index), 'starts_at' => '18:00', 'finishes_at' => '20:00'];
        }
        $booking = app(CreateConcertBooking::class)->execute($data, $studio);

        return [$booking, $studio, $staff, $customer];
    }
}
