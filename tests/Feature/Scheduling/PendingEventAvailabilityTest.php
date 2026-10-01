<?php

namespace Tests\Feature\Scheduling;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\CrewMobileAvailability;
use App\Features\Scheduling\Services\EventAvailability;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PendingEventAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_requests_appear_between_scheduled_shifts_with_review_links_and_no_crew_controls(): void
    {
        $studio = Studio::factory()->create();
        $early = $this->scheduled($studio, 'Morning confirmed', '09:00');
        $late = $this->scheduled($studio, 'Evening confirmed', '19:00');
        $item = $this->pending($studio);
        CrewProfile::factory()->for(User::factory()->crew()->create())->create();
        $response = $this->actingAs(User::factory()->staff()->create())->get(route('admin.scheduling-events.index'))
            ->assertOk()->assertSeeInOrder(['Morning confirmed', $item->title, 'Evening confirmed'])
            ->assertSee('Pending approval')->assertSee('Crew availability not open')->assertSee('3:00 pm')
            ->assertSee(route('admin.concert-bookings.show', $item->booking), false);
        preg_match('/<tr class="pending-booking-row".*?<\/tr>/s', $response->getContent(), $pendingRow);
        $this->assertStringNotContainsString('<select', $pendingRow[0]);
        $this->assertStringNotContainsString('<input', $pendingRow[0]);
        $this->assertSame([$early->id, $late->id], $response->viewData('shifts')->pluck('scheduling_event_id')->all());
        $this->assertNull($item->fresh()->scheduling_event_id);
        $this->assertDatabaseCount('scheduling_events', 2);
        $this->assertDatabaseCount('crew_notifications', 0);
    }

    public function test_pending_filters_exclude_cancelled_declined_and_already_scheduled_requests(): void
    {
        $studio = Studio::factory()->create();
        $type = EventTypeDefinition::create(['name' => 'Portrait test', 'code' => 'PTEST', 'system_category' => 'concert', 'is_active' => true]);
        $item = $this->pending($studio, ['item_type' => 'dress_rehearsal', 'event_type_definition_id' => $type->id, 'title' => 'Requested portraits']);
        $other = $this->pending(Studio::factory()->create(), ['title' => 'Other studio']);
        $this->pending($studio, ['cancelled_at' => now(), 'title' => 'Cancelled item']);
        $declined = $this->pending($studio, ['title' => 'Declined booking']);
        $declined->booking->update(['status' => 'declined']);
        $cancelled = $this->pending($studio, ['title' => 'Cancelled booking']);
        $cancelled->booking->update(['status' => 'cancelled']);
        $scheduled = $this->scheduled($studio, 'Confirmed event', '10:00');
        $this->pending($studio, ['scheduling_event_id' => $scheduled->id, 'title' => 'Already scheduled']);
        $item->booking->update(['status' => 'approved']); // New pending items on partly approved bookings remain visible.
        $page = app(EventAvailability::class);
        $this->assertSame(3, $page->page()['rows']->total());
        foreach ([['studio' => $studio->uuid, 'status' => 'pending'], ['search' => 'Requested portraits'], ['type' => $type->uuid]] as $filters) {
            $rows = $page->page($filters)['rows'];
            $this->assertSame([$item->uuid], $rows->pluck('item.uuid')->all());
        }
        $this->assertSame([$other->uuid], $page->page(['studio' => $other->booking->studio->uuid])['rows']->pluck('item.uuid')->all());
        $this->assertSame(['shift'], $page->page(['status' => 'draft'])['rows']->pluck('row_type')->all());
        $this->assertSame(0, $page->page(['status' => 'open'])['rows']->total());
        $this->assertSame(['shift'], $page->page([], $scheduled)['rows']->pluck('row_type')->all());
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.scheduling-events.index', ['status' => 'pending']))
            ->assertOk()->assertSee('Requested portraits')->assertDontSee('Confirmed event')->assertDontSee('Cancelled item');
    }

    public function test_approval_replaces_pending_row_without_opening_crew_availability(): void
    {
        Mail::fake();
        $staff = User::factory()->staff()->create();
        $crew = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crew)->create();
        $item = $this->pending(Studio::factory()->create());
        $this->actingAs($crew)->get(route('crew.availability.index'))->assertOk()->assertDontSee($item->booking->studio_name);
        $this->assertCount(0, app(CrewMobileAvailability::class)->for($profile));
        $this->putJson(route('crew.availability.store', $item->uuid), ['status' => 'available'])->assertNotFound();
        $event = app(ApproveConcertBooking::class)->approveItem($item, $staff);
        $rows = app(EventAvailability::class)->page()['rows'];
        $this->assertSame(['shift'], $rows->pluck('row_type')->all());
        $this->assertSame($event->id, $rows->first()->item->scheduling_event_id);
        $this->assertSame('draft', $event->availability_status->value);
        $this->assertCount(0, app(CrewMobileAvailability::class)->for($profile));
        $event->update(['availability_status' => 'open', 'availability_deadline' => now()->addWeek()]);
        $this->assertCount(1, app(CrewMobileAvailability::class)->for($profile));
        Mail::assertNothingSent();
    }

    public function test_pending_and_scheduled_rows_share_one_chronological_pagination(): void
    {
        $studio = Studio::factory()->create();
        for ($index = 0; $index < 49; $index++) {
            $this->scheduled($studio, 'Earlier event '.$index, '09:00');
        }
        $item = $this->pending($studio);
        $last = $this->scheduled($studio, 'Last event', '19:00');
        $this->actingAs(User::factory()->staff()->create());
        $first = $this->get(route('admin.scheduling-events.index', ['studio' => $studio->uuid]))->assertOk();
        $this->assertSame(51, $first->viewData('rows')->total());
        $this->assertSame($item->uuid, $first->viewData('rows')->last()->item->uuid);
        $first->assertSee($item->title)->assertDontSee('Last event');
        $second = $this->get(route('admin.scheduling-events.index', ['studio' => $studio->uuid, 'page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('rows'));
        $this->assertSame($last->id, $second->viewData('rows')->first()->item->scheduling_event_id);
        $second->assertDontSee($item->title);
    }

    private function pending(Studio $studio, array $overrides = []): ConcertBookingItem
    {
        $venue = Venue::create(['name' => 'Shared theatre']);
        $booking = ConcertBooking::create(['studio_id' => $studio->id, 'studio_name' => $studio->name, 'contact_name' => 'Jess Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000']);

        return $booking->items()->create($overrides + ['item_type' => 'concert', 'title' => 'Pending afternoon showcase', 'venue_id' => $venue->id, 'venue_name' => $venue->name, 'venue_address' => '1 Stage Road', 'event_date' => today()->addMonth(), 'starts_at' => '15:00', 'finishes_at' => '17:00'])->fresh();
    }

    private function scheduled(Studio $studio, string $name, string $time): SchedulingEvent
    {
        $event = SchedulingEvent::create(['studio_id' => $studio->id, 'name' => $name, 'event_type' => 'concert', 'event_date' => today()->addMonth()]);
        $event->shifts()->create(['shift_date' => $event->event_date, 'starts_at' => $event->event_date->format('Y-m-d').' '.$time]);

        return $event;
    }
}
