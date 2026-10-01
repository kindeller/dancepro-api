<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Mail\BookingSubmitted;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\BookingSubmissionAcknowledgements;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingSubmissionAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_emails_account_once_and_shows_pending_events_and_studio_notification(): void
    {
        Mail::fake();
        [$user, $studio, $data] = $this->bookingData();
        $this->actingAs($user)->post(route('concert-bookings.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $booking = ConcertBooking::firstOrFail();
        $this->assertSame('sent', $booking->submission_acknowledgement['status']);
        Mail::assertSent(BookingSubmitted::class, fn ($mail) => $mail->hasTo($user->email) && ! $mail->hasTo($data['contact_email']));
        $mail = new BookingSubmitted($booking->submission_acknowledgement);
        $html = $mail->render();
        $this->assertStringContainsString('pending approval', $html);
        $this->assertStringContainsString('notify you by email', $html);
        $this->assertStringContainsString('dancepro-logo', $html);
        $message = StudioConversationMessage::firstOrFail();
        $this->assertNull($message->studio_read_at);
        $this->get(route('customer.studio-communications.index', [$studio, 'tab' => 'events']))->assertOk()
            ->assertSee('Pending approval')->assertSee('Spring showcase')->assertDontSee('No concert events yet');
        $this->assertNull($message->fresh()->studio_read_at);
        $this->get(route('customer.studio-communications.index', [$studio, 'tab' => 'messages']))->assertOk()
            ->assertSee('Booking received')->assertSee('pending approval')->assertSee('Booking notification');
        $this->assertNull($message->fresh()->studio_read_at);
        $this->postJson(route('customer.studio-communications.read', [$studio, 'message', $message->uuid]))->assertOk();
        $this->assertNotNull($message->fresh()->studio_read_at);
        $this->post(route('concert-bookings.store'), $data)->assertRedirect();
        Mail::assertSentCount(1);
        $this->assertSame(1, StudioConversationMessage::count());
        $booking->items()->update(['approval_status' => 'approved']);
        $this->get(route('customer.studio-communications.index', [$studio, 'tab' => 'events']))->assertDontSee('Pending approval');
    }

    public function test_mail_failure_does_not_lose_the_booking_and_retry_does_not_duplicate_the_studio_message(): void
    {
        [$user, $studio, $data] = $this->bookingData();
        $originalMailer = Mail::getFacadeRoot();
        Mail::partialMock()->shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated mail failure'));
        $this->actingAs($user)->post(route('concert-bookings.store'), $data)->assertRedirect();
        $booking = ConcertBooking::firstOrFail();
        $this->assertNotNull($booking->submission_acknowledgement, 'Receipt should be saved after the failed send.');
        $this->assertSame('failed', $booking->submission_acknowledgement['status']);
        $this->assertSame(1, StudioConversationMessage::count());
        Mail::swap($originalMailer);
        Mail::fake();
        app(BookingSubmissionAcknowledgements::class)->send($booking, $user);
        $this->assertNotNull($booking->fresh()->submission_acknowledgement, 'Receipt should remain saved after retry.');
        $this->assertSame('sent', $booking->fresh()->submission_acknowledgement['status']);
        Mail::assertSentCount(1);
        $this->assertSame(1, StudioConversationMessage::count());
    }

    public function test_guest_receipt_does_not_promise_access_to_an_unlinked_studio(): void
    {
        Mail::fake();
        [$user, $studio, $data] = $this->bookingData();
        unset($data['studio']);
        $this->post(route('concert-bookings.store'), $data)->assertRedirect();
        Mail::assertSent(BookingSubmitted::class, fn ($mail) => $mail->hasTo($data['contact_email']) && ! $mail->receipt['content']['hasStudio']);
        $this->assertSame(0, StudioConversationMessage::count());
    }

    public function test_studio_shortcut_and_collapsed_quote_estimates_use_shared_buttons(): void
    {
        Mail::fake();
        [$user, $studio, $data] = $this->bookingData();
        $this->actingAs($user)->post(route('concert-bookings.store'), $data)->assertRedirect();
        $this->get(route('customer.messages.index'))->assertOk()->assertSee('>Studio messages</a>', false);
        $this->get(route('customer.studio-hub', ['tab' => 'messages']))
            ->assertRedirect(route('customer.studio-communications.index', [$studio, 'tab' => 'messages']));
        $response = $this->get(route('customer.studio-invoices.index', $studio))->assertOk()
            ->assertSee('Quote estimates &amp; minimum spends', false)->assertSee('My booking')->assertSee('My timeline');
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*\bopen\b/', $response->getContent());
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.messages.index'))
            ->assertOk()->assertDontSee('>Studio messages</a>', false);
    }

    private function bookingData(): array
    {
        $user = User::factory()->customer()->create(['first_name' => 'Jess', 'last_name' => 'Director']);
        $studio = Studio::factory()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'active', 'role' => 'director']);
        $venue = Venue::create(['name' => 'Test Hall', 'address_line_1' => '1 Example Road']);

        return [$user, $studio, [
            'studio' => $studio->uuid, 'studio_name' => $studio->name, 'contact_name' => 'Other contact',
            'contact_email' => 'contact@example.test', 'contact_phone' => '0400000000',
            'wants_concert_photography' => '0', 'wants_concert_videography' => '0',
            'accepted_requirements' => ['accurate', 'portrait_space', 'no_personal_backdrop_photos'],
            'items' => [['item_type' => 'dress_rehearsal', 'title' => 'Spring showcase', 'venue_uuid' => $venue->uuid,
                'event_date' => today()->addMonth()->toDateString(), 'starts_at' => '16:00', 'finishes_at' => '18:00']],
        ]];
    }
}
