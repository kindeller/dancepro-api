<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Actions\CreateConcertTrackerAlerts;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingTrackerBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_page_always_has_bar_and_old_task_link_redirects_after_approval(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()->assertSee('Tracker task controls')->assertSee('1.1 Booking &amp; services', false)
            ->assertSee('Automatic from booking')->assertSee('Edit booking');
        $this->assertDatabaseCount('concert_tracker_tasks', 0);
        $this->assertDatabaseCount('scheduling_events', 0);
        $this->get(route('admin.concert-tracker.index', ['view' => 'date', 'year' => 2026, 'phase' => 'booking']))
            ->assertOk()->assertSee('class="badge tracker-not-approved">Not approved', false)
            ->assertSee('class="tracker-status" href="'.route('admin.concert-bookings.show', $booking).'"', false);
        $this->get(route('admin.concert-bookings.show', $booking))->assertSee(route('admin.concert-tracker.index', ['view' => 'date', 'year' => 2026, 'phase' => 'booking', 'attention' => false]));

        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'booking_received']))
            ->assertRedirect(route('admin.concert-bookings.show', $booking));
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('Tracker task controls')->assertSee('Approved');
        $row = app(ConcertTracker::class)->page('date', 2026, 'booking')['rows']->first();
        $this->assertSame(route('admin.concert-bookings.show', $booking), $row['tasks']['booking_received']['detailUrl']);
    }

    public function test_each_box_updates_only_its_fields_and_task_survives_approval(): void
    {
        [$staff, $booking] = $this->fixture();
        $crew = CrewProfile::factory()->create()->user;
        $url = route('admin.concert-bookings.tracker.update', $booking);
        $this->actingAs($staff);
        foreach ([
            ['panel' => 'assignment', 'assignee_user_id' => $crew->id],
            ['panel' => 'due', 'due_date_override' => '2026-10-01'],
            ['panel' => 'follow_up', 'follow_up_status' => 'waiting', 'follow_up_date' => '2026-10-02', 'note' => 'Waiting for venue details.'],
            ['panel' => 'status', 'status_override' => 'complete', 'assignee_user_id' => null],
        ] as $input) {
            $this->patch($url, $input)->assertRedirect(route('admin.concert-bookings.show', $booking))->assertSessionHasNoErrors();
        }
        $task = ConcertTrackerTask::firstOrFail();
        $this->assertSame($crew->id, $task->assignee_user_id);
        $this->assertSame('2026-10-01', $task->due_date_override->toDateString());
        $this->assertSame('waiting', $task->follow_up_status);
        $this->assertSame('2026-10-02', $task->follow_up_date->toDateString());
        $this->assertSame('Waiting for venue details.', $task->notes->sole()->body);
        $this->assertSame('pending', $booking->fresh()->status->value);
        $this->assertDatabaseCount('scheduling_events', 0);
        $this->assertSame(4, DB::table('concert_tracker_task_changes')->where('concert_tracker_task_id', $task->id)->count());
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('Manual override')->assertSee('Waiting for venue details.');
        $pendingRow = app(ConcertTracker::class)->page('date', 2026, 'booking')['rows']->first();
        $this->assertSame('complete', $pendingRow['bookingStatus']['key']);
        $this->assertTrue($pendingRow['pending']);
        $this->get(route('admin.concert-tracker.index', ['view' => 'date', 'year' => 2026, 'phase' => 'booking']))
            ->assertOk()->assertSee('class="badge tracker-not-approved">Not approved', false);

        $this->patch($url, ['panel' => 'status', 'status_override' => 'waiting'])->assertSessionHasNoErrors();
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        $status = app(ConcertTracker::class)->detail($event, 'booking_received')['status'];
        $this->assertSame('waiting', $status['key']);
        $this->assertSame($crew->id, $status['assignee']->id);
        $this->assertSame($task->id, $status['override']->id);
        $this->patch($url, ['panel' => 'status', 'status_override' => null])->assertSessionHasNoErrors();
        $this->assertSame('Approved', app(ConcertTracker::class)->detail($event, 'booking_received')['status']['label']);
        $this->assertSame(1, $task->notes()->count());
        $this->patch($url, ['panel' => 'due', 'due_date_override' => null])->assertSessionHasNoErrors();
        $this->patch($url, ['panel' => 'assignment', 'assignee_user_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->due_date_override);
        $this->assertNull($task->fresh()->assignee_user_id);
    }

    public function test_bar_can_save_without_reloading_the_booking_form(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff)->patchJson(route('admin.concert-bookings.tracker.update', $booking), [
            'panel' => 'due', 'due_date_override' => '2026-10-01',
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Tracker updated.')
            ->assertJsonStructure(['data' => ['html']]);
        $this->assertSame('2026-10-01', ConcertTrackerTask::firstOrFail()->due_date_override->toDateString());
        $this->patchJson(route('admin.concert-bookings.tracker.update', $booking), [
            'panel' => 'due', 'due_date_override' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('due_date_override');
    }

    public function test_bar_rejects_invalid_inputs_and_customer_access(): void
    {
        [$staff, $booking] = $this->fixture();
        $customer = User::factory()->customer()->create();
        $inactive = User::factory()->staff()->inactive()->create();
        $url = route('admin.concert-bookings.tracker.update', $booking);
        $this->actingAs($staff);
        foreach ([$customer, $inactive] as $person) {
            $this->patch($url, ['panel' => 'assignment', 'assignee_user_id' => $person->id])->assertSessionHasErrors('assignee_user_id');
        }
        $this->patch($url, ['panel' => 'status', 'status_override' => 'unknown'])->assertSessionHasErrors('status_override');
        $this->patch($url, ['panel' => 'due', 'due_date_override' => 'not-a-date'])->assertSessionHasErrors('due_date_override');
        $this->patch($url, ['panel' => 'follow_up', 'follow_up_status' => 'unknown', 'follow_up_date' => null])->assertSessionHasErrors('follow_up_status');
        $this->assertDatabaseCount('concert_tracker_tasks', 0);
        $this->actingAs($customer)->patch($url, ['panel' => 'status', 'status_override' => 'complete'])->assertForbidden();
        $this->get(route('admin.concert-bookings.show', $booking))->assertForbidden();
    }

    public function test_follow_up_notes_are_escaped_and_scoped_to_the_booking(): void
    {
        [$staff, $booking] = $this->fixture();
        [, $other] = $this->fixture();
        $this->actingAs($staff)->patch(route('admin.concert-bookings.tracker.update', $booking), [
            'panel' => 'follow_up', 'follow_up_status' => 'open', 'follow_up_date' => '2026-09-01', 'note' => '<script>alert("note")</script>',
        ])->assertSessionHasNoErrors();
        $this->get(route('admin.concert-bookings.show', $booking))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("note")</script>', false);
        $this->get(route('admin.concert-bookings.show', $other))->assertOk()->assertDontSee('&lt;script&gt;', false);
        $event = app(ApproveConcertBooking::class)->approveItem($booking->items->first(), $staff);
        // Keep the task open so the attention filter can exercise follow-up resolution.
        $this->patch(route('admin.concert-bookings.tracker.update', $booking), ['panel' => 'status', 'status_override' => 'waiting']);
        $tracker = app(ConcertTracker::class);
        $this->assertCount(1, $tracker->page('date', 2026, 'booking', true)['rows']);
        $attentionBefore = $tracker->page('date', 2026, 'booking')['summary']['attention'];
        app(CreateConcertTrackerAlerts::class)->execute();
        $this->assertDatabaseHas('concert_tracker_alerts', ['concert_tracker_task_id' => ConcertTrackerTask::firstOrFail()->id, 'type' => 'follow_up_due', 'resolved_at' => null]);
        $this->patch(route('admin.concert-bookings.tracker.update', $booking), ['panel' => 'follow_up', 'follow_up_status' => 'resolved', 'follow_up_date' => '2026-09-01', 'note' => 'Venue confirmed.'])->assertSessionHasNoErrors();
        $task = ConcertTrackerTask::firstOrFail();
        $this->assertCount(2, $task->notes);
        $this->assertSame('resolved', $task->follow_up_status);
        app(CreateConcertTrackerAlerts::class)->execute();
        $this->assertDatabaseMissing('concert_tracker_alerts', ['concert_tracker_task_id' => $task->id, 'type' => 'follow_up_due', 'resolved_at' => null]);
        $this->assertSame($attentionBefore - 1, $tracker->page('date', 2026, 'booking')['summary']['attention']);
        $this->assertSame('waiting', $tracker->detail($event, 'booking_received')['status']['key']);
    }

    public function test_pending_booking_tabs_have_their_own_read_only_guidance_and_navigation(): void
    {
        [$staff, $booking] = $this->fixture();
        $this->actingAs($staff);
        foreach (['programme' => '2.2 Prepare programme', 'video-titles' => '2.3 Video titles', 'studio-media' => '2.5 Studio videos & slideshows'] as $tab => $label) {
            $response = $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => $tab]));
            $response->assertOk()->assertSee('Task navigation')->assertSee('Back to tracker')->assertSee('Back to event')
                ->assertSee('<strong>'.e($label).'</strong>', false)->assertSee('Task instructions')
                ->assertSee('Approve and link the applicable event before completing this task.');
        }
        $this->assertDatabaseCount('concert_tracker_tasks', 0);
    }

    public function test_ajax_updates_keep_the_same_navigation_and_instructions(): void
    {
        [$staff, $booking] = $this->fixture();
        $response = $this->actingAs($staff)->withSession(['concert_tracker_context' => ['year' => 2026, 'phase' => 'booking', 'view' => 'studio']])
            ->patchJson(route('admin.concert-bookings.tracker.update', $booking), ['panel' => 'due', 'due_date_override' => '2026-10-01'])->assertOk();
        $html = $response->json('data.html');
        $this->assertStringContainsString('Task navigation', $html);
        $this->assertStringContainsString('Back to event', $html);
        $this->assertStringContainsString('Task instructions', $html);
        $this->assertStringContainsString(e(route('admin.concert-tracker.index', ['year' => 2026, 'phase' => 'booking', 'view' => 'studio'])), $html);
    }

    private function fixture(): array
    {
        Queue::fake();
        Notification::fake();
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $booking = ConcertBooking::create([
            'status' => 'pending', 'studio_id' => $studio->id, 'studio_name' => $studio->name,
            'contact_name' => 'Example Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000',
            'wants_concert_photography' => true, 'wants_concert_videography' => false,
        ]);
        $venue = Venue::create(['name' => 'Example theatre', 'address_line_1' => '1 Example Road']);
        $booking->items()->create(['item_type' => 'concert', 'title' => 'Junior concert', 'event_date' => '2026-10-20', 'venue_id' => $venue->id, 'venue_name' => $venue->name, 'venue_address' => '1 Example Road', 'starts_at' => '18:00', 'finishes_at' => '20:00']);

        return [$staff, $booking->fresh('items')];
    }
}
