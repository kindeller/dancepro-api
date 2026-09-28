<?php

namespace Tests\Feature\Tracking;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\ConcertQualityChecklist;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcertQualityChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_checks_autosave_and_completion_reopens_when_unchecked(): void
    {
        [$staff, $event] = $this->fixture();
        $checklist = app(ConcertQualityChecklist::class);
        $task = app(ConcertTracker::class)->detail($event, 'quality_check')['task'];
        $page = route('admin.concert-tracker.tasks.show', [$event, 'quality_check']);
        $url = route('admin.concert-tracker.quality.update', $event);
        $this->actingAs($staff)->get($page)->assertOk()->assertSee('Each tick or untick saves automatically.')->assertSee('id="quality-complete-all"', false);
        $selected = [];
        foreach ($checklist->keys() as $key) {
            $selected[] = $key;
            $this->patchJson($url, ['checks' => $selected, 'fingerprint' => $checklist->fingerprint($task->fresh())])
                ->assertOk()->assertJsonPath('data.checked', count($selected));
        }
        $task->refresh();
        $this->assertSame('complete', $task->status);
        $this->assertSame($staff->id, $task->completed_by_user_id);
        $this->assertSame('complete', app(ConcertTracker::class)->detail($event->fresh(), 'quality_check')['status']['tone']);
        $firstAudit = $task->quality_checks[$selected[0]];
        $oldFingerprint = $checklist->fingerprint($task);
        array_pop($selected);
        $this->patchJson($url, ['checks' => $selected, 'fingerprint' => $oldFingerprint])->assertOk()->assertJsonPath('data.complete', false);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame($firstAudit, $task->fresh()->quality_checks[$selected[0]]);
        $this->patchJson($url, ['checks' => [], 'fingerprint' => $oldFingerprint])->assertUnprocessable();
        $this->assertCount(19, $task->fresh()->quality_checks);
        $this->patchJson(route('admin.concert-tracker.source-bar.update', [$event, 'quality_check']), ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
    }

    public function test_complete_all_is_admin_only_and_staff_cannot_bypass_it_with_a_bulk_payload(): void
    {
        [$staff, $event] = $this->fixture();
        $staff->update(['is_admin' => false]);
        $checklist = app(ConcertQualityChecklist::class);
        $task = app(ConcertTracker::class)->detail($event, 'quality_check')['task'];
        $url = route('admin.concert-tracker.quality.update', $event);
        $payload = ['checks' => $checklist->keys(), 'fingerprint' => $checklist->fingerprint($task), 'complete_all' => true];
        $this->actingAs($staff)->patchJson($url, $payload)->assertForbidden();
        unset($payload['complete_all']);
        $this->patchJson($url, $payload)->assertForbidden();
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'quality_check']))->assertForbidden();
        // The app's Admin group includes admin-enabled staff and crew accounts.
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin)->get(route('admin.concert-tracker.tasks.show', [$event, 'quality_check']))->assertOk()->assertSee('id="quality-complete-all"', false);
        $this->patchJson($url, $payload + ['complete_all' => true])->assertOk()->assertJsonPath('data.complete', true);
        $this->assertSame($admin->id, $task->fresh()->completed_by_user_id);
        $this->actingAs(User::factory()->customer()->create())->patchJson($url, $payload)->assertForbidden();
    }

    private function fixture(): array
    {
        $staff = User::factory()->staff()->create();
        $event = SchedulingEvent::create(['name' => 'Quality concert', 'event_type' => 'concert', 'event_date' => today()->addDays(10)]);
        $booking = ConcertBooking::create(['status' => 'approved', 'studio_name' => 'Quality studio', 'contact_name' => 'Director',
            'contact_email' => 'quality@example.test', 'contact_phone' => '0400000000', 'wants_concert_videography' => true]);
        ConcertBookingItem::create(['concert_booking_id' => $booking->id, 'scheduling_event_id' => $event->id,
            'item_type' => 'concert', 'title' => $event->name, 'event_date' => $event->event_date, 'starts_at' => '18:00', 'finishes_at' => '20:00', 'venue_name' => 'Theatre', 'venue_address' => 'Street']);

        return [$staff, $event];
    }
}
