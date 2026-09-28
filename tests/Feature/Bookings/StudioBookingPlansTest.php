<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioBookingPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_booking_plan_and_original_form_survive_a_new_session(): void
    {
        [$user, $studio] = $this->studioAccount();
        $this->actingAs($user)->get(route('customer.studio-communications.index', $studio))
            ->assertOk()->assertSee('Event timeline')->assertSee(route('customer.studio-bookings.index', $studio), false);
        $this->get(route('concert-bookings.create', ['studio' => $studio->uuid]))
            ->assertOk()->assertSee('name="studio" value="'.$studio->uuid.'"', false);
        $response = $this->post(route('concert-bookings.store'), $this->data($studio));
        $booking = ConcertBooking::query()->firstOrFail();
        $response->assertRedirect(route('customer.studio-bookings.show', [$studio, $booking]));
        $this->assertSame($studio->id, $booking->studio_id);
        $this->assertSame('pending', $booking->status->value);

        $this->flushSession();
        $this->get(route('customer.studio-bookings.index', $studio))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Portrait session')
            ->assertSee(route('customer.studio-bookings.show', [$studio, $booking]), false);
        $this->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Event timeline')
            ->assertSee(route('customer.studio-bookings.submission', [$studio, $booking]), false)
            ->assertSee(route('customer.studio-bookings.index', $studio), false);
        $this->get(route('customer.studio-bookings.submission', [$studio, $booking]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Your submitted booking')
            ->assertSee(route('customer.studio-bookings.show', [$studio, $booking]), false);
    }

    public function test_other_studio_members_and_revoked_members_cannot_read_receipts(): void
    {
        [$owner, $studio] = $this->studioAccount();
        $this->actingAs($owner)->post(route('concert-bookings.store'), $this->data($studio))->assertRedirect();
        $booking = ConcertBooking::query()->firstOrFail();
        [$other, $otherStudio] = $this->studioAccount();
        $this->flushSession();
        $this->actingAs($other)->get(route('customer.studio-bookings.index', $studio))->assertForbidden();
        foreach (['show', 'submission'] as $action) {
            $this->get(route('customer.studio-bookings.'.$action, [$studio, $booking]))->assertForbidden();
            $this->get(route('customer.studio-bookings.'.$action, [$otherStudio, $booking]))->assertNotFound();
        }
        $this->get(route('customer.studio-bookings.index', $otherStudio))->assertOk()->assertDontSee('Portrait session');
        StudioAccountAccess::query()->where('studio_id', $studio->id)->update(['status' => 'revoked']);
        $this->actingAs($owner)->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertForbidden();
    }

    public function test_booking_cannot_be_attached_to_an_unauthorised_studio(): void
    {
        [$owner, $studio] = $this->studioAccount();
        $this->post(route('concert-bookings.store'), $this->data($studio))->assertForbidden();
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('concert-bookings.store'), $this->data($studio))->assertForbidden();
        $this->assertDatabaseCount('concert_bookings', 0);
    }

    public function test_older_saved_booking_uses_actual_event_studio_links_not_name_or_email(): void
    {
        [$user, $studio] = $this->studioAccount();
        $data = $this->data($studio);
        unset($data['studio']);
        $this->actingAs($user)->post(route('concert-bookings.store'), $data)->assertRedirect();
        $booking = ConcertBooking::query()->firstOrFail();
        $this->get(route('customer.studio-bookings.index', $studio))->assertOk()->assertDontSee('Portrait session');
        $event = SchedulingEvent::query()->create(['name' => 'Linked event', 'event_type' => 'concert', 'event_date' => today()->addMonth(), 'studio_id' => $studio->id]);
        $booking->items()->firstOrFail()->update(['scheduling_event_id' => $event->id]);
        $this->flushSession();
        $this->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertOk()->assertSee('Event timeline');

        $anotherStudio = Studio::factory()->create();
        $booking->update(['studio_id' => $anotherStudio->id]);
        $this->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertNotFound();
    }

    public function test_legacy_booking_spanning_different_studios_does_not_expose_the_whole_submission(): void
    {
        [$user, $studio] = $this->studioAccount();
        $data = $this->data($studio);
        unset($data['studio']);
        $data['items'][] = [...$data['items'][0], 'title' => 'Another session'];
        $this->actingAs($user)->post(route('concert-bookings.store'), $data)->assertRedirect();
        $booking = ConcertBooking::query()->firstOrFail();
        foreach ([$studio, Studio::factory()->create()] as $index => $eventStudio) {
            $event = SchedulingEvent::query()->create(['name' => 'Linked event', 'event_type' => 'concert', 'event_date' => today()->addMonth(), 'studio_id' => $eventStudio->id]);
            $booking->items[$index]->update(['scheduling_event_id' => $event->id]);
        }
        $this->get(route('customer.studio-bookings.show', [$studio, $booking]))->assertNotFound();
    }

    private function studioAccount(): array
    {
        $user = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => 'active', 'role' => 'director']);

        return [$user, $studio];
    }

    private function data(Studio $studio): array
    {
        $venue = Venue::query()->firstOrCreate(['name' => 'Example Hall'], ['address_line_1' => '1 Example Road']);

        return [
            'studio' => $studio->uuid, 'studio_name' => $studio->name,
            'contact_name' => 'Booking director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000',
            'wants_concert_photography' => '0', 'wants_concert_videography' => '0',
            'accepted_requirements' => ['accurate', 'portrait_space', 'no_personal_backdrop_photos'],
            'items' => [['item_type' => 'dress_rehearsal', 'title' => 'Portrait session', 'venue_uuid' => $venue->uuid,
                'event_date' => today()->addMonth()->toDateString(), 'starts_at' => '16:00', 'finishes_at' => '18:00']],
        ];
    }
}
