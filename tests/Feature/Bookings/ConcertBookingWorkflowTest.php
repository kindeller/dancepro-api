<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcertBookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_collects_repeatable_concert_details(): void
    {
        $this->get(route('concert-bookings.create'))
            ->assertOk()->assertSee('Studio/company name')->assertSee('dress rehearsal portrait photography')
            ->assertSee('Concert videography')->assertSee('Event details')->assertSee('Add another event')
            ->assertSee('DR Portrait')->assertSee('Concert title (optional)')
            ->assertSee('Other venue')->assertSee('General booking confirmation')
            ->assertSee('id="video-delivery"', false)
            ->assertDontSee('Our concerts include');

        $this->post(route('concert-bookings.store'), $this->bookingData())
            ->assertRedirect(route('concert-bookings.thanks'));

        $booking = ConcertBooking::query()->firstOrFail();
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()->assertSee('A Night of Dance')->assertSee('Example Theatre');
        $this->assertSame(2, $booking->items()->count());
        $this->assertTrue($booking->items->every(fn (ConcertBookingItem $item): bool => $item->venue_id !== null));
        $this->assertTrue($booking->wants_concert_videography);
        $this->assertSame('pending', $booking->status->value);
    }

    public function test_concert_title_is_optional_but_video_delivery_fields_are_required_for_videography(): void
    {
        $data = $this->bookingData();
        $data['items'][1]['title'] = '';
        unset($data['postal_address'], $data['previous_video_feedback']);

        $this->post(route('concert-bookings.store'), $data)
            ->assertSessionHasErrors(['postal_address', 'previous_video_feedback'])
            ->assertSessionDoesntHaveErrors('items.1.title');

        $data['postal_address'] = '1 Example Road, Perth WA 6000';
        $data['previous_video_feedback'] = 'No previous issues.';

        $this->post(route('concert-bookings.store'), $data)
            ->assertRedirect(route('concert-bookings.thanks'));

        $this->assertNull(ConcertBookingItem::query()->where('item_type', 'concert')->firstOrFail()->title);
    }

    public function test_approval_creates_draft_events_without_opening_availability(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $booking = ConcertBooking::query()->firstOrFail();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking), [
            'internal_review_note' => 'Details checked by phone.',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('approved', $booking->status->value);
        $this->assertDatabaseCount('scheduling_events', 2);
        $this->assertSame(2, SchedulingEvent::query()->where('availability_status', 'draft')->count());
        $this->assertSame(2, SchedulingEvent::query()->where('name', 'Fictional Dance Academy')->count());
        $this->assertSame(0, SchedulingEvent::query()->whereHas('shifts', fn ($query) => $query->whereNotNull('period'))->count());
        $this->assertDatabaseCount('scheduling_event_role_requirements', 4);
        $dressRehearsal = $booking->items()->where('item_type', 'dress_rehearsal')->firstOrFail()->schedulingEvent;
        $concert = $booking->items()->where('item_type', 'concert')->firstOrFail()->schedulingEvent;
        $this->assertEqualsCanonicalizing(
            ['photographer-p', 'concert-dr-portrait-assistant'],
            $dressRehearsal->roleRequirements()->with('crewRole')->get()->pluck('crewRole.code')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['concert-photographer-p1', 'concert-videographer'],
            $concert->roleRequirements()->with('crewRole')->get()->pluck('crewRole.code')->all(),
        );
        $this->assertDatabaseCount('crew_availability_responses', 0);
    }

    public function test_staff_can_review_and_selectively_reconcile_booking_contact_discrepancies(): void
    {
        $studio = Studio::factory()->create(['name' => 'Fictional Dance Academy']);
        $contact = $studio->contacts()->create([
            'name' => 'Morgan Previous',
            'role' => 'Administrator',
            'emails' => ['studio@example.test'],
            'phone' => '0400 999 999',
            'position' => 0,
        ]);
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $booking = ConcertBooking::query()->firstOrFail();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()
            ->assertSee('Studio contact check')
            ->assertSee('2 discrepancies found')
            ->assertSee('Morgan Previous')
            ->assertSee('Morgan Example')
            ->assertSee('0400 999 999')
            ->assertSee('0400 000 100');

        $this->actingAs($staff)->post(route('admin.concert-bookings.reconcile-contact', $booking), [
            'studio_uuid' => $studio->uuid,
            'action' => 'update',
            'fields' => ['phone', 'role'],
            'role' => 'Studio Manager',
        ])->assertRedirect();

        $contact->refresh();
        $this->assertSame('Morgan Previous', $contact->name);
        $this->assertSame('0400 000 100', $contact->phone);
        $this->assertSame('Studio Manager', $contact->role);
        $this->assertSame('Morgan Example', $booking->fresh()->contact_name);

        $this->actingAs($staff)->post(route('admin.concert-bookings.reconcile-contact', $booking), [
            'studio_uuid' => $studio->uuid,
            'action' => 'add',
            'role' => 'Owner',
        ])->assertRedirect();

        $this->assertDatabaseHas('studio_contacts', [
            'studio_id' => $studio->id,
            'name' => 'Morgan Example',
            'role' => 'Owner',
            'phone' => '0400 000 100',
        ]);
    }

    public function test_staff_can_create_a_prefilled_studio_from_an_unmatched_booking(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $booking = ConcertBooking::query()->firstOrFail();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()
            ->assertSee('No matching studio record found.')
            ->assertSee('Create studio from this booking')
            ->assertSee('value="Fictional Dance Academy"', false)
            ->assertSee('value="Morgan Example"', false)
            ->assertSee('value="studio@example.test"', false)
            ->assertSee('value="0400 000 100"', false);

        $this->actingAs($staff)->post(route('admin.concert-bookings.create-studio', $booking), [
            'name' => 'Fictional Dance Academy',
            'code' => 'FDA',
            'slug' => '',
            'status' => 'active',
            'brand_color' => '#112233',
            'description' => 'Created and edited during booking review.',
            'notes' => 'Confirm logo later.',
            'contacts' => [[
                'name' => 'Morgan Example',
                'role' => 'Event Coordinator',
                'emails' => 'studio@example.test',
                'phone' => '0400 000 100',
            ]],
        ])->assertRedirect();

        $studio = Studio::query()->where('code', 'FDA')->firstOrFail();
        $this->assertSame('Fictional Dance Academy', $studio->name);
        $this->assertSame('#112233', $studio->brand_color);
        $this->assertSame('Morgan Example', $studio->contacts->first()->name);
        $this->assertSame('Event Coordinator', $studio->contacts->first()->role);
        $this->assertSame('Morgan Example', $booking->fresh()->contact_name);
    }

    public function test_events_can_be_approved_and_opened_in_bulk_with_a_five_pm_deadline(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $booking = ConcertBooking::query()->firstOrFail();
        $items = $booking->items()->orderBy('id')->get();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->patch(route('admin.concert-booking-events.bulk-status'), [
            'event_ids' => [$items[0]->uuid],
            'action' => 'approve',
        ])->assertRedirect();

        $this->assertSame('approved', $items[0]->refresh()->approval_status);
        $this->assertSame('pending', $items[1]->refresh()->approval_status);
        $this->assertSame('pending', $booking->refresh()->status->value);

        $this->actingAs($staff)->patch(route('admin.concert-booking-events.bulk-status'), [
            'event_ids' => [$items[1]->uuid],
            'action' => 'approve',
        ])->assertRedirect();

        $this->assertSame('approved', $booking->refresh()->status->value);

        $this->actingAs($staff)->patch(route('admin.concert-booking-events.bulk-status'), [
            'event_ids' => $items->pluck('uuid')->all(),
            'action' => 'open',
            'deadline_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $events = SchedulingEvent::query()->get();
        $this->assertCount(2, $events);
        $this->assertTrue($events->every(fn (SchedulingEvent $event): bool => $event->availability_status->value === 'open'));
        $this->assertTrue($events->every(fn (SchedulingEvent $event): bool => $event->availability_deadline->format('H:i') === '17:00'));
    }

    public function test_event_list_shows_every_studio_submission_as_its_own_row(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $items = ConcertBookingItem::query()->get();

        $response = $this->actingAs($staff)->get(route('admin.concert-bookings.index'));

        $response->assertOk()->assertSee('A Night of Dance')->assertSee('Dress rehearsal');
        foreach ($items as $item) {
            $response->assertSee($item->uuid);
        }
    }

    public function test_event_creation_starts_with_the_event_type_decision(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.scheduling-events.create'))
            ->assertOk()->assertSee('Competition')->assertSee('Concert');
        $this->actingAs($staff)->get(route('admin.scheduling-events.create', ['type' => 'competition']))
            ->assertOk()->assertSee('Competition name')->assertSee('Add competition day')
            ->assertSee("getElementById('add-day')", false);
    }

    public function test_managed_concert_type_is_selected_and_carried_into_the_approved_event(): void
    {
        $eventType = EventTypeDefinition::query()->create([
            'code' => 'dr-portrait',
            'name' => 'DR Portrait',
            'system_category' => 'concert',
            'is_active' => true,
        ]);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.scheduling-events.create'))
            ->assertOk()->assertSee('DR Portrait');
        $this->get(route('concert-bookings.create', ['event_type' => $eventType->uuid]))
            ->assertOk()->assertSee('value="'.$eventType->id.'" selected', false);

        $data = $this->bookingData();
        $data['items'] = [
            ...$data['items'][0],
            'event_type_definition_id' => $eventType->id,
        ];
        $data['items'] = [$data['items']];

        $this->post(route('concert-bookings.store'), $data)
            ->assertRedirect(route('concert-bookings.thanks'));

        $booking = ConcertBooking::query()->firstOrFail();
        $item = $booking->items()->firstOrFail();
        $this->assertSame($eventType->id, $item->event_type_definition_id);

        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking))
            ->assertRedirect();

        $event = $item->refresh()->schedulingEvent;
        $this->assertSame($eventType->id, $event->event_type_definition_id);
        $this->assertSame('concert', $event->event_type->value);
    }

    public function test_other_venue_requires_staff_resolution_and_is_not_created_on_submission(): void
    {
        $data = $this->bookingData();
        $data['items'][0] = [
            ...$data['items'][0],
            'venue_uuid' => 'other',
            'venue_name' => 'Proposed Community Theatre',
            'venue_address' => '10 New Venue Road, Perth WA',
        ];

        $this->post(route('concert-bookings.store'), $data)->assertRedirect(route('concert-bookings.thanks'));

        $booking = ConcertBooking::query()->firstOrFail();
        $item = $booking->items()->orderBy('id')->firstOrFail();
        $staff = User::factory()->staff()->create();
        $this->assertNull($item->venue_id);
        $this->assertDatabaseMissing('venues', ['name' => 'Proposed Community Theatre']);

        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking))
            ->assertSessionHasErrors('venue');
        $this->assertDatabaseMissing('venues', ['name' => 'Proposed Community Theatre']);

        $this->actingAs($staff)->put(route('admin.concert-booking-events.venue.update', $item), [
            'resolution_action' => 'create',
        ])->assertRedirect();

        $this->assertDatabaseHas('venues', ['name' => 'Proposed Community Theatre', 'address_line_1' => '10 New Venue Road, Perth WA']);
        $this->assertNotNull($item->refresh()->venue_id);
    }

    private function bookingData(): array
    {
        $hall = Venue::query()->firstOrCreate(['name' => 'Example Hall'], ['address_line_1' => '1 Example Road']);
        $theatre = Venue::query()->firstOrCreate(['name' => 'Example Theatre'], ['address_line_1' => '2 Example Road']);

        return [
            'studio_name' => 'Fictional Dance Academy', 'contact_name' => 'Morgan Example',
            'contact_email' => 'studio@example.test', 'contact_phone' => '0400 000 100',
            'portrait_photography_interest' => 'yes', 'wants_concert_photography' => '1',
            'wants_concert_videography' => '1', 'approximate_family_count' => 120,
            'postal_address' => '1 Example Road, Perth WA 6000',
            'previous_video_feedback' => 'Clear chapter navigation worked well.',
            'concert_inclusions' => ['presentations_awards', 'tap_dance'],
            'accepted_requirements' => ['accurate', 'portrait_space', 'no_personal_backdrop_photos', 'minimum_spend', 'photographer_seat', 'no_audience_recording', 'promote_gallery', 'credit_images', 'video_access', 'video_space_audio', 'programme_invoice'],
            'items' => [
                ['item_type' => 'dress_rehearsal', 'title' => '', 'venue_uuid' => $hall->uuid, 'event_date' => now()->addMonth()->toDateString(), 'starts_at' => '17:00', 'finishes_at' => '20:00'],
                ['item_type' => 'concert', 'title' => 'A Night of Dance', 'venue_uuid' => $theatre->uuid, 'event_date' => now()->addMonths(2)->toDateString(), 'starts_at' => '18:00', 'finishes_at' => '21:00'],
            ],
        ];
    }
}
