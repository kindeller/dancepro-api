<?php

namespace Tests\Feature\Bookings;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcertBookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expanded_event_displays_booking_details_and_edit_link(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData())->assertRedirect();
        $booking = ConcertBooking::query()->firstOrFail();
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.concert-bookings.approve', $booking))->assertRedirect();
        $item = $booking->items()->oldest('id')->firstOrFail();
        $item->update(['programme_path' => 'programmes/test.pdf', 'programme_name' => 'Studio run sheet.pdf']);
        $item->venue->update(['access_notes' => 'Use stage door', 'parking_notes' => 'Crew bays at rear', 'operational_notes' => 'Power at stage left']);
        $booking->update(['internal_review_note' => '<script>private note</script>', 'multiple_concert_relationship' => 'Different age groups', 'concert_inclusions' => ['presentations_awards', 'tap_dance']]);

        $this->get(route('admin.scheduling-events.show', $item->schedulingEvent))->assertOk()
            ->assertSee('Morgan Example')->assertSee('studio@example.test')->assertSee('0400 000 100')
            ->assertSee('Portrait photography')->assertSee('Concert videography')->assertSee('120')
            ->assertSee('Presentations / awards')->assertSee('Tap dance')
            ->assertSee('1 Example Road, Perth WA 6000')->assertSee('Clear chapter navigation worked well.')
            ->assertSee('Different age groups')->assertSee('No personal backdrop photos')
            ->assertSee('17:00–20:00')->assertSee('Studio run sheet.pdf')
            ->assertSee(route('admin.concert-booking-events.programme.show', $item), false)
            ->assertSee('Use stage door')->assertSee('Crew bays at rear')->assertSee('Power at stage left')
            ->assertSee('Other events in this booking (1)')->assertSee('A Night of Dance')
            ->assertSee('&lt;script&gt;private note&lt;/script&gt;', false)->assertDontSee('<script>private note</script>', false)
            ->assertSee(route('admin.concert-bookings.show', $booking).'#event-'.$item->uuid, false)
            ->assertSee('Edit booking');
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()
            ->assertSee('id="event-'.$item->uuid.'"', false);
    }

    public function test_booking_and_pending_lists_show_matched_studio_artwork_or_a_placeholder(): void
    {
        Storage::fake('public');
        config(['uploads.public_disk' => 'public']);
        $this->post(route('concert-bookings.store'), $this->bookingData())->assertRedirect();
        $studio = Studio::factory()->create([
            'name' => 'fictional dance academy', 'logo_path' => 'studios/booking-artwork.png',
        ]);
        $this->actingAs(User::factory()->staff()->create());

        foreach (['admin.concert-bookings.index', 'admin.event-management.pending'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('src="'.url($studio->logoUrl()).'"', false);
        }

        $studio->update(['logo_path' => null]);
        foreach (['admin.concert-bookings.index', 'admin.event-management.pending'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('No artwork for Fictional Dance Academy')
                ->assertDontSee('booking-artwork.png');
        }

        $studio->update(['name' => 'Another studio', 'logo_path' => 'studios/booking-artwork.png']);
        $this->get(route('admin.event-management.pending'))->assertOk()
            ->assertDontSee('booking-artwork.png')->assertSee('No artwork for Fictional Dance Academy');
    }

    public function test_approved_booking_artwork_uses_the_linked_event_instead_of_the_submitted_studio_name(): void
    {
        Storage::fake('public');
        config(['uploads.public_disk' => 'public']);
        $this->post(route('concert-bookings.store'), $this->bookingData())->assertRedirect();
        Studio::factory()->create(['name' => 'Fictional Dance Academy', 'logo_path' => 'studios/old.png']);
        $event = SchedulingEvent::query()->create([
            'name' => 'Linked event', 'event_type' => 'concert', 'event_date' => today(), 'logo_path' => 'events/fallback.png',
        ]);
        ConcertBookingItem::query()->update(['scheduling_event_id' => $event->id, 'approval_status' => 'approved']);

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.concert-bookings.index'))
            ->assertOk()->assertSee('src="'.url($event->logoUrl()).'"', false)->assertDontSee('studios/old.png');
    }

    public function test_expanded_admin_event_information_uses_organisation_artwork_and_fallbacks(): void
    {
        Storage::fake('public');
        config(['uploads.public_disk' => 'public']);
        $this->actingAs(User::factory()->staff()->create());
        $studio = Studio::factory()->create(['logo_path' => 'studios/expanded.png']);
        $competition = CompetitionContact::query()->create([
            'name' => 'Artwork competition', 'logo_path' => 'competitions/expanded.png',
            'organiser_name' => 'Organiser', 'organiser_email' => 'artwork@example.com', 'organiser_phone' => '0400 000 000',
        ]);
        foreach (['concert' => $studio, 'competition' => $competition] as $type => $organisation) {
            $event = SchedulingEvent::query()->create([
                'name' => 'Expanded '.$type, 'event_type' => $type, 'event_date' => today(),
                'studio_id' => $studio->id, 'competition_contact_id' => $competition->id, 'logo_path' => 'events/fallback.png',
            ]);
            $this->get(route('admin.scheduling-events.show', $event))->assertOk()
                ->assertSee('src="'.url($organisation->logoUrl()).'"', false)->assertDontSee('events/fallback.png');
            $organisation->update(['logo_path' => null]);
            $this->get(route('admin.scheduling-events.show', $event))->assertOk()
                ->assertSee('src="'.url($event->logoUrl()).'"', false);
            $event->update(['logo_path' => null]);
            $this->get(route('admin.scheduling-events.show', $event))->assertOk()->assertSee('No artwork for Expanded '.$type);
        }
    }

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

    public function test_public_booking_rejects_honeypot_submissions(): void
    {
        $this->post(route('concert-bookings.store'), [
            ...$this->bookingData(),
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('concert_bookings', 0);
    }

    public function test_identical_booking_submissions_are_deduplicated_within_the_safety_window(): void
    {
        $data = $this->bookingData();

        $this->post(route('concert-bookings.store'), $data)->assertRedirect(route('concert-bookings.thanks'));
        $this->post(route('concert-bookings.store'), $data)->assertRedirect(route('concert-bookings.thanks'));

        $this->assertDatabaseCount('concert_bookings', 1);
        $this->assertDatabaseCount('concert_booking_items', 2);
        $this->assertNotNull(ConcertBooking::query()->firstOrFail()->submission_fingerprint);
    }

    public function test_public_booking_submissions_are_rate_limited(): void
    {
        $data = $this->bookingData();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('concert-bookings.store'), $data)->assertRedirect(route('concert-bookings.thanks'));
        }

        $this->post(route('concert-bookings.store'), $data)->assertTooManyRequests();
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
        $studio = Studio::factory()->create(['name' => 'Fictional Dance Academy']);

        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking), [
            'internal_review_note' => 'Details checked by phone.',
            'studio_uuid' => $studio->uuid,
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('approved', $booking->status->value);
        $this->assertDatabaseCount('scheduling_events', 2);
        $this->assertSame(2, SchedulingEvent::query()->where('availability_status', 'draft')->count());
        $this->assertSame(2, SchedulingEvent::query()->where('name', 'Fictional Dance Academy')->count());
        $this->assertSame(2, SchedulingEvent::query()->where('studio_id', $studio->id)->count());
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

        $this->actingAs($staff)->get(route('admin.concert-bookings.index'))
            ->assertOk()
            ->assertSee('>Edit</a>', false)
            ->assertSee('>Review</a>', false);

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

        $response->assertOk()->assertSee('A Night of Dance')->assertSee('Dress rehearsal')->assertSee('Review')->assertDontSee('>Edit</a>', false);
        foreach ($items as $item) {
            $response->assertSee($item->uuid);
        }

        $this->actingAs($staff)->get(route('admin.event-management.pending'))
            ->assertOk()
            ->assertSee('badge pending', false)
            ->assertSee('Pending approval');

        $this->actingAs($staff)->get(route('admin.concert-bookings.show', ConcertBooking::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Approve booking')
            ->assertSee(route('admin.concert-bookings.approve', ConcertBooking::query()->firstOrFail()), false);
    }

    public function test_admin_can_attach_open_and_replace_an_event_programme(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $item = ConcertBookingItem::query()->firstOrFail();

        $this->actingAs($staff)->get(route('admin.concert-bookings.index'))
            ->assertOk()
            ->assertSee('Attach Programme')
            ->assertSee('name="programme"', false)
            ->assertSee('enctype="multipart/form-data"', false);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $item->booking))
            ->assertOk()
            ->assertSee('Attach Programme')
            ->assertSee('data-programme-picker="programme-'.$item->uuid.'"', false);

        $this->actingAs($staff)->post(route('admin.concert-booking-events.programme.store', $item), [
            'programme' => UploadedFile::fake()->create('first-programme.pdf', 40, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('status', 'Event programme attached.');
        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $item->booking))->assertRedirect();

        $item->refresh();
        $firstPath = $item->programme_path;
        Storage::disk('local')->assertExists($firstPath);
        $this->assertSame($firstPath, $item->schedulingEvent->programme_path);
        $this->actingAs($staff)->get(route('admin.concert-booking-events.programme.show', $item))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=first-programme.pdf');
        $this->actingAs($staff)->get(route('admin.concert-bookings.index'))
            ->assertOk()->assertSee('Open Programme')->assertSee('Replace Programme');
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $item->booking))
            ->assertOk()->assertSee('Open Programme')->assertSee('Replace Programme');

        $this->actingAs($staff)->post(route('admin.concert-booking-events.programme.store', $item), [
            'programme' => UploadedFile::fake()->create('replacement.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($item->refresh()->programme_path);
        $this->assertSame('replacement.pdf', $item->programme_name);
        $this->assertSame($item->programme_path, $item->schedulingEvent->programme_path);
    }

    public function test_event_programmes_are_pdf_only_and_admin_private(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $item = ConcertBookingItem::query()->firstOrFail();

        $this->actingAs($staff)->post(route('admin.concert-booking-events.programme.store', $item), [
            'programme' => UploadedFile::fake()->image('not-a-programme.jpg'),
        ])->assertSessionHasErrors('programme');

        $crew = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($crew)->post(route('admin.concert-booking-events.programme.store', $item), [
            'programme' => UploadedFile::fake()->create('programme.pdf', 20, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($crew)->get(route('admin.concert-booking-events.programme.show', $item))->assertForbidden();
        $this->assertNull($item->refresh()->programme_path);
    }

    public function test_admin_can_edit_all_booking_and_event_details(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $booking = ConcertBooking::query()->with('items')->firstOrFail();
        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking))->assertRedirect();
        $crewUser = User::factory()->crew()->create();
        $crewProfile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::factory()->create();
        $assignment = $booking->items()->oldest('id')->firstOrFail()->schedulingEvent->shifts()->firstOrFail()->assignments()->create([
            'crew_profile_id' => $crewProfile->id,
            'crew_role_id' => $role->id,
            'status' => 'published',
            'acknowledgement_status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
        $venue = Venue::query()->where('name', 'Example Theatre')->firstOrFail();
        $items = $booking->items->values()->map(fn (ConcertBookingItem $item, int $index): array => [
            'uuid' => $item->uuid,
            'item_type' => $index === 0 ? 'dress_rehearsal' : 'concert',
            'event_type_definition_id' => $item->event_type_definition_id,
            'title' => $index === 0 ? 'Updated rehearsal' : 'Updated concert',
            'venue_uuid' => $venue->uuid,
            'event_date' => now()->addMonths(3 + $index)->toDateString(),
            'starts_at' => '16:30',
            'finishes_at' => '20:30',
        ])->all();

        $this->actingAs($staff)->put(route('admin.concert-bookings.update', $booking), [
            'studio_name' => 'Updated Dance Academy', 'contact_name' => 'Updated Contact',
            'contact_email' => 'updated@example.test', 'contact_phone' => '0411 222 333',
            'portrait_photography_interest' => 'unsure', 'wants_portrait_photography' => '0',
            'wants_concert_photography' => '1', 'wants_concert_videography' => '0',
            'approximate_family_count' => 145, 'postal_address' => 'New postal address',
            'previous_video_feedback' => 'Updated feedback', 'concert_inclusions' => ['tap_dance'],
            'multiple_concert_relationship' => 'Same dancers', 'accepted_requirements' => ['accurate'],
            'internal_review_note' => 'Details confirmed by phone.', 'items' => $items,
        ])->assertRedirect()->assertSessionHas('status', 'Booking details updated.');

        $booking->refresh();
        $this->get(route('admin.scheduling-events.show', $assignment->shift->schedulingEvent))->assertOk()
            ->assertSee('Updated Contact')->assertSee('updated@example.test')->assertSee('0411 222 333')
            ->assertSee('Updated rehearsal')->assertSee('16:30–20:30')
            ->assertSee('Updated feedback')->assertSee('Details confirmed by phone.');
        $this->assertSame('Updated Dance Academy', $booking->studio_name);
        $this->assertSame('updated@example.test', $booking->contact_email);
        $this->assertFalse($booking->wants_concert_videography);
        $this->assertSame(['tap_dance'], $booking->concert_inclusions);
        $this->assertSame('Updated rehearsal', $booking->items()->oldest('id')->first()->title);
        $this->assertSame('16:30:00', $booking->items()->oldest('id')->first()->starts_at);
        $this->assertSame('reset_by_material_change', $assignment->refresh()->acknowledgement_status);
        $this->assertNull($assignment->acknowledged_at);
        $this->assertDatabaseHas('crew_notifications', [
            'user_id' => $crewUser->id,
            'type' => 'shift_changed',
            'destination_id' => $assignment->uuid,
        ]);
    }

    public function test_admin_can_safely_cancel_and_hide_a_pending_booking(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $booking = ConcertBooking::query()->firstOrFail();

        $this->actingAs($staff)->post(route('admin.concert-bookings.cancel', $booking))
            ->assertRedirect(route('admin.concert-bookings.index'));
        $this->assertSame('cancelled', $booking->refresh()->status->value);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertTrue($booking->items->every(fn (ConcertBookingItem $item): bool => $item->approval_status === 'cancelled'));
        $this->actingAs($staff)->get(route('admin.concert-bookings.index'))->assertOk()->assertDontSee($booking->studio_name);
    }

    public function test_admin_can_cancel_one_approved_event_without_removing_the_rest_of_the_booking(): void
    {
        $this->post(route('concert-bookings.store'), $this->bookingData());
        $staff = User::factory()->staff()->create();
        $booking = ConcertBooking::query()->firstOrFail();

        $this->actingAs($staff)->post(route('admin.concert-bookings.approve', $booking))->assertRedirect();
        $cancelledItem = $booking->items()->oldest('id')->firstOrFail();
        $remainingItem = $booking->items()->latest('id')->firstOrFail();
        $cancelledEvent = $cancelledItem->schedulingEvent;
        $crewUser = User::factory()->crew()->create();
        $crewProfile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::factory()->create();
        $assignment = $cancelledEvent->shifts()->firstOrFail()->assignments()->create([
            'crew_profile_id' => $crewProfile->id,
            'crew_role_id' => $role->id,
            'status' => 'published',
        ]);
        $obsoleteNotification = CrewNotification::query()->create([
            'user_id' => $crewUser->id,
            'type' => 'shift_allocation',
            'title' => 'New shift',
            'message' => 'Please acknowledge.',
            'destination_type' => 'assignment',
            'destination_id' => $assignment->uuid,
        ]);

        $this->actingAs($staff)->post(route('admin.concert-booking-events.cancel', $cancelledItem))
            ->assertRedirect(route('admin.concert-bookings.show', $booking))
            ->assertSessionHas('status', 'Booking event cancelled.');

        $this->assertSame('cancelled', $cancelledItem->refresh()->approval_status);
        $this->assertNotNull($cancelledItem->cancelled_at);
        $this->assertNotNull($cancelledEvent->refresh()->cancelled_at);
        $this->assertSame('closed', $cancelledEvent->availability_status->value);
        $this->assertSame('approved', $remainingItem->refresh()->approval_status);
        $this->assertSame('approved', $booking->refresh()->status->value);
        $notification = CrewNotification::query()->where('user_id', $crewUser->id)->where('type', 'event_cancelled')->firstOrFail();
        $this->assertSame('assignment', $notification->destination_type);
        $this->assertSame($assignment->uuid, $notification->destination_id);
        $this->assertNotNull($obsoleteNotification->refresh()->read_at);

        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()->assertSee('No shifts here.')->assertDontSee('cancelled-shift-card', false);
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'cancelled']))
            ->assertOk()->assertSee($cancelledEvent->name)->assertSee('Cancelled')->assertSee('text-decoration:line-through', false);
        $this->actingAs($crewUser)->get(route('crew.assignments.show', $assignment))
            ->assertOk()->assertSee('This shift has been cancelled')->assertDontSee('Organise cover')->assertDontSee('Post message');

        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()->assertSee('Cancelled')->assertSee($remainingItem->title);
        $this->actingAs($staff)->get(route('admin.scheduling-events.index'))
            ->assertOk()->assertSee('cancelled-event-row')->assertSee('Cancelled');
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
