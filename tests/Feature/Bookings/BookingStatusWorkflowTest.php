<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Mail\BookingStatusMessage;
use App\Features\Bookings\Models\BookingStatusNotification;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_is_yellow_unscheduled_and_optional_email_is_branded_logged_and_not_duplicated(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff)->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'received'])->assertOk();
        $this->assertSame('received', $booking->fresh()->status->value);
        $this->assertDatabaseCount('scheduling_events', 0);
        $this->assertSame('near-due', app(BookingTrackerBar::class)->page($booking->fresh())['statusTone']);
        Mail::assertNothingSent();
        $email = BookingStatusNotification::sole();
        $page = route('admin.concert-bookings.status-email', [$booking, $email]);
        $this->get($page)->assertOk()->assertSee('Received')->assertSee('without sending')->assertSee('data-email-send-editor', false)->assertSee('Reset to original message');
        $send = route('admin.concert-bookings.status-email.send', [$booking, $email]);
        $input = ['recipient_email' => $booking->contact_email, 'subject' => 'Thanks for booking early', 'body' => 'Hi Director, we have received your booking.'];
        $this->from($page)->post($send, $input)->assertRedirect($page)->assertSessionHasNoErrors();
        Mail::assertSent(BookingStatusMessage::class, function ($mail) use ($staff): bool {
            $html = $mail->render();
            $this->assertStringContainsString('DancePro Photography', $html);
            $this->assertStringContainsString('Log into your DancePro account', $html);
            $this->assertStringContainsString(e($staff->name), $html);

            return true;
        });
        $this->assertSame('sent', $email->fresh()->delivery_status);
        $this->assertSame($staff->id, $email->fresh()->sent_by_user_id);
        $message = StudioConversationMessage::sole();
        $this->assertSame($input['subject'], $message->subject);
        $this->assertNull($message->studio_read_at);
        $this->post($send, $input)->assertSessionHasErrors('email');
        Mail::assertSentCount(1);
        $this->get($page)->assertSee('Email sent')->assertDontSee('Send studio notification');
    }

    public function test_partial_approval_only_schedules_selected_events_and_full_approval_finishes_the_booking(): void
    {
        [$staff, $booking] = $this->fixture();
        $items = $booking->items;
        $this->actingAs($staff)->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'partial_approval', 'event_ids' => [$items[0]->uuid]])->assertOk();
        $this->assertSame('partial_approval', $booking->fresh()->status->value);
        $this->assertNotNull($items[0]->fresh()->scheduling_event_id);
        $this->assertNull($items[1]->fresh()->scheduling_event_id);
        $this->assertSame('draft', SchedulingEvent::sole()->availability_status->value);
        $this->assertSame('complete', app(BookingTrackerBar::class)->page($booking->fresh())['statusTone']);
        $this->assertStringContainsString('Pending approval', BookingStatusNotification::sole()->body);
        $this->assertStringContainsString('Approved', BookingStatusNotification::sole()->body);
        $this->assertCount(2, app(ConcertTracker::class)->page('date', 2026, 'booking')['rows']);
        $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'received'])->assertUnprocessable();
        $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'approved'])->assertOk()->assertJsonPath('data.redirect', route('admin.concert-bookings.confirmation', $booking));
        $this->assertSame('approved', $booking->fresh()->status->value);
        $this->assertDatabaseCount('scheduling_events', 2);
        Mail::assertNothingSent();
    }

    public function test_partial_selection_cannot_include_other_bookings_or_all_events_and_validates_venues_atomically(): void
    {
        [$staff, $booking] = $this->fixture();
        [, $other] = $this->fixture();
        $this->actingAs($staff);
        foreach ([[], [$other->items[0]->uuid], $booking->items->pluck('uuid')->all()] as $ids) {
            $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'partial_approval', 'event_ids' => $ids])->assertUnprocessable()->assertJsonValidationErrors('event_ids');
        }
        $booking->items[0]->update(['venue_id' => null]);
        $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'partial_approval', 'event_ids' => [$booking->items[0]->uuid]])->assertUnprocessable()->assertJsonValidationErrors('venue');
        $this->assertDatabaseCount('scheduling_events', 0);
        $this->assertSame('pending', $booking->fresh()->status->value);
    }

    public function test_cancel_requires_reason_hides_events_and_records_editable_email(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff)->post(route('admin.concert-bookings.cancel', $booking))->assertSessionHasErrors('reason');
        $this->assertSame('pending', $booking->fresh()->status->value);
        $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'cancelled', 'reason' => 'Studio changed its plans.'])->assertOk();
        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertSame('not_required', app(BookingTrackerBar::class)->page($booking->fresh())['statusTone']);
        $this->assertTrue($booking->items()->get()->every(fn ($item) => $item->approval_status === 'cancelled'));
        $this->assertCount(0, app(ConcertTracker::class)->page('date', 2026, 'booking')['rows']);
        $email = BookingStatusNotification::sole();
        $this->assertSame('Studio changed its plans.', $email->reason);
        $this->get(route('admin.concert-bookings.status-email', [$booking, $email]))->assertOk()->assertSee('Studio changed its plans.')->assertSee('Send studio notification');
        $this->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'pending'])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_stale_email_and_cross_booking_or_customer_access_are_blocked(): void
    {
        [$staff, $booking] = $this->fixture();
        [, $other] = $this->fixture();
        $this->actingAs($staff)->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'received'])->assertOk();
        $email = BookingStatusNotification::sole();
        $this->get(route('admin.concert-bookings.status-email', [$other, $email]))->assertNotFound();
        $booking->items[0]->update(['title' => 'Changed title']);
        $this->post(route('admin.concert-bookings.status-email.send', [$booking, $email]), ['recipient_email' => $booking->contact_email, 'subject' => 'Received', 'body' => 'Thanks'])->assertSessionHasErrors('email');
        $this->actingAs(User::factory()->customer()->create())->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'approved'])->assertForbidden();
        $this->get(route('admin.concert-bookings.status-email', [$booking, $email]))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_received_is_visible_in_studio_hub_without_a_description(): void
    {
        [$staff, $booking] = $this->fixture();
        $booking->forceFill(['submission_snapshot' => ['details' => ['items' => []]]])->save();
        $owner = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $booking->studio_id, 'user_id' => $owner->id, 'email' => $owner->email, 'status' => 'active', 'role' => 'director']);
        $this->actingAs($staff)->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'received'])->assertOk();
        $this->actingAs($owner)->get(route('customer.studio-communications.index', [$booking->studio, 'tab' => 'events']))
            ->assertOk()->assertSee('hub-badge warning">Received', false)->assertDontSee('DancePro is reviewing this request.');
        $this->assertSame(0, app(ConcertTracker::class)->page('date', 2026, 'booking')['summary']['attention']);
    }

    public function test_failed_email_can_be_retried_without_changing_the_booking(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff)->patchJson($this->statusUrl($booking), ['panel' => 'status', 'status_override' => 'received'])->assertOk();
        $email = BookingStatusNotification::sole();
        $send = route('admin.concert-bookings.status-email.send', [$booking, $email]);
        $input = ['recipient_email' => $booking->contact_email, 'subject' => 'Received', 'body' => 'Thanks'];
        $mail = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Test send failure'));
        $this->post($send, $input)->assertSessionHasErrors('email');
        $this->assertSame('failed', $email->fresh()->delivery_status);
        $this->assertSame('received', $booking->fresh()->status->value);
        $this->assertDatabaseCount('studio_conversation_messages', 0);
        Mail::swap($mail);
        $this->post($send, $input)->assertSessionHasNoErrors();
        $this->assertSame('sent', $email->fresh()->delivery_status);
        $this->assertDatabaseCount('studio_conversation_messages', 1);
    }

    private function statusUrl(ConcertBooking $booking): string
    {
        return route('admin.concert-bookings.tracker.update', $booking);
    }

    private function fixture(): array
    {
        Mail::fake();
        Queue::fake();
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $booking = ConcertBooking::create(['status' => 'pending', 'studio_id' => $studio->id, 'studio_name' => $studio->name, 'contact_name' => 'Example Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000', 'wants_concert_photography' => true]);
        $venue = Venue::create(['name' => 'Theatre', 'address_line_1' => '1 Example Road']);
        foreach (['Junior', 'Senior'] as $title) {
            $booking->items()->create(['item_type' => 'concert', 'title' => $title, 'event_date' => '2026-10-20', 'venue_id' => $venue->id, 'venue_name' => $venue->name, 'venue_address' => '1 Example Road', 'starts_at' => '18:00', 'finishes_at' => '20:00']);
        }

        return [$staff, $booking->fresh('items')];
    }
}
