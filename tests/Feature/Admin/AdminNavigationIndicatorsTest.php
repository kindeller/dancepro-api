<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Services\AdminNavigationIndicators;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminNavigationIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_badges_roll_up_once_and_match_their_tabs(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(10, 0));
        $staff = User::factory()->staff()->create();
        CrewProfile::factory()->for($staff)->create();
        $customer = User::factory()->customer()->create();
        $studio = Studio::factory()->create();
        $this->mock(AdminExceptionOverview::class)->shouldReceive('all')->andReturn(collect([
            ['severity' => 'action'], ['severity' => 'action'], ['severity' => 'check'],
        ]));
        $this->mock(CrewNavigationIndicators::class)->shouldReceive('for')->andReturn(['shifts' => 3, 'timesheets' => 5, 'chat' => 7]);
        $booking = ConcertBooking::create(['status' => 'pending', 'studio_name' => 'Pending studio', 'contact_name' => 'Director', 'contact_email' => 'director@example.test', 'contact_phone' => '0400000000']);
        foreach (['2026-10-01', '2027-01-01'] as $date) {
            $booking->items()->create(['item_type' => 'concert', 'venue_name' => 'Theatre', 'venue_address' => 'Test address', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'title' => 'Awaiting approval '.$date, 'event_date' => $date, 'approval_status' => 'pending']);
        }
        $booking->items()->create(['item_type' => 'concert', 'venue_name' => 'Theatre', 'venue_address' => 'Test address', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'title' => 'Cancelled item', 'event_date' => '2026-10-02', 'approval_status' => 'pending', 'cancelled_at' => now()]);
        $event = SchedulingEvent::create(['name' => 'Already scheduled', 'event_type' => 'concert', 'event_date' => '2026-10-03']);
        $booking->items()->create(['item_type' => 'concert', 'venue_name' => 'Theatre', 'venue_address' => 'Test address', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'title' => 'Already scheduled item', 'event_date' => '2026-10-03', 'approval_status' => 'pending', 'scheduling_event_id' => $event->id]);
        foreach (['cancelled', 'declined'] as $state) {
            $closed = $booking->replicate(['uuid']);
            $closed->status = $state;
            $closed->save();
            $closed->items()->create(['item_type' => 'concert', 'venue_name' => 'Theatre', 'venue_address' => 'Test address', 'starts_at' => '18:00', 'finishes_at' => '20:00', 'title' => 'Closed booking item', 'event_date' => '2026-10-02', 'approval_status' => 'pending']);
        }
        foreach (['pending', 'pending', 'resolved', 'approved', 'declined'] as $status) {
            CustomerSupportRequest::create(['user_id' => $customer->id, 'type' => 'general_help', 'status' => $status, 'subject' => 'Help', 'message' => 'Please help', 'requested_at' => now()]);
        }
        foreach ([now()->subDay(), now(), now()->addMinute(), null] as $due) {
            CustomerAccountNote::create(['user_id' => $customer->id, 'created_by_user_id' => $staff->id, 'note' => 'Follow up', 'follow_up_at' => $due]);
        }
        CustomerAccountNote::create(['user_id' => $customer->id, 'note' => 'Already done', 'follow_up_at' => now()->subDay(), 'completed_at' => now()]);
        foreach ([['to_dancepro', null], ['to_dancepro', now()], ['to_studio', null]] as [$direction, $read]) {
            StudioConversationMessage::create(['studio_id' => $studio->id, 'direction' => $direction, 'subject' => 'Message', 'body' => 'Hello', 'sent_at' => now(), 'staff_read_at' => $read]);
        }
        $this->actingAs($staff);
        $page = $this->get(route('admin.event-management.pending'))->assertOk();
        foreach (['my_hub' => 3, 'exceptions' => 2, 'pending_events' => 2, 'hub_management' => 4, 'support_pending' => 2, 'follow_ups_due' => 2, 'customers' => 4, 'studio_messages' => 1] as $key => $count) {
            $this->assertBadges($page, $key, $count, $key === 'pending_events' ? 2 : 1);
        }
        $this->assertSame(2, $page->viewData('items')->total());
        $page->assertDontSee('Cancelled item')->assertDontSee('Closed booking item')->assertDontSee('Already scheduled item');
        $this->assertBadges($page, 'pending_events', 2, 2);
        $this->assertBadges($this->get(route('admin.media-support.index', ['status' => 'resolved']))->assertOk(), 'support_pending', 2, 2);
        $duePage = $this->get(route('admin.customers.follow-ups'))->assertOk();
        $this->assertSame(2, $duePage->viewData('notes')->total());
        $this->assertBadges($duePage, 'follow_ups_due', 2, 2);
        $this->assertBadges($this->get(route('admin.customers.follow-ups', ['scope' => 'upcoming']))->assertOk(), 'follow_ups_due', 2, 2);

        // Resolved/read/approved work disappears on the next page load at every level.
        CustomerSupportRequest::where('status', 'pending')->update(['status' => 'resolved']);
        CustomerAccountNote::where('follow_up_at', '<=', now())->update(['completed_at' => now()]);
        StudioConversationMessage::where('direction', 'to_dancepro')->update(['staff_read_at' => now()]);
        $booking->items()->whereNull('cancelled_at')->update(['approval_status' => 'approved']);
        $page = $this->get(route('admin.event-management.pending'))->assertOk();
        foreach (['pending_events', 'support_pending', 'follow_ups_due', 'customers', 'studio_messages'] as $key) {
            $this->assertBadges($page, $key, 0);
        }
        $this->assertBadges($page, 'hub_management', 2);
        $this->assertSame(0, $page->viewData('items')->total());
    }

    public function test_indicators_do_not_expose_admin_queues_to_customers_or_inactive_users(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->staff()->create(['is_active' => false])] as $user) {
            $this->assertSame(0, array_sum(app(AdminNavigationIndicators::class)->for($user)));
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    private function assertBadges(TestResponse $response, string $key, int $count, int $occurrences = 1): void
    {
        $doc = new \DOMDocument;
        @$doc->loadHTML($response->getContent());
        $badges = (new \DOMXPath($doc))->query('//*[@data-notification-key="'.$key.'"]');
        $this->assertCount($count === 0 ? 0 : $occurrences, $badges, $key);
        foreach ($badges as $badge) {
            $this->assertSame((string) $count, $badge->textContent, $key);
        }
    }
}
