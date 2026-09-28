<?php

namespace Tests\Feature\Programmes;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Programmes\Models\ConcertTitleCardTemplate;
use App\Features\Programmes\Models\Programme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcertTitleCardTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_preview_and_save_a_concert_title_card_template(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create(['brand_color' => '#123456']);
        $event = SchedulingEvent::create([
            'studio_id' => $studio->id,
            'name' => 'A Night to Remember - Senior Concert 2026',
            'event_type' => 'concert',
            'event_date' => '2026-09-24',
        ]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $programme->nodes()->create([
            'kind' => 'item', 'sort_order' => 1, 'is_active' => true,
            'data' => ['uuid' => '91000000-0000-4000-8000-000000000001', 'kind' => 'item',
                'folder_number' => '01', 'title' => 'Waiting for Tonight', 'class_name' => '12 & Under Jazz'],
        ]);
        $booking = ConcertBooking::create([
            'wants_concert_videography' => true,
            'studio_name' => 'Centre Stage Dance & Entertainment - DEMO',
            'contact_name' => 'Demo Director',
            'contact_email' => 'director@example.test',
            'contact_phone' => '0400 000 000',
        ]);
        ConcertBookingItem::create([
            'concert_booking_id' => $booking->id,
            'scheduling_event_id' => $event->id,
            'item_type' => 'concert',
            'title' => 'Junior Showcase',
            'venue_name' => 'Demo Theatre',
            'venue_address' => '1 Example Street',
            'event_date' => '2026-09-24',
            'starts_at' => '18:00',
            'finishes_at' => '20:00',
        ]);

        $url = route('admin.concert-bookings.show', [$booking, 'tab' => 'video-titles', 'event' => $event->uuid]);
        $this->actingAs($staff)->get(route('admin.concert-title-cards.show', $event))->assertRedirect($url);
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'video_titles']))->assertRedirect(route('admin.concert-title-cards.show', $event));
        $this->get($url)
            ->assertOk()->assertSee('Video titles ready')->assertSee('2.3 Video titles')->assertSee('Tracker task controls')->assertSee('Title card template')->assertSee('Waiting for Tonight')
            ->assertSee('12 \\u0026 Under Jazz', false)->assertSee('Atlanta')
            ->assertSee('3840 × 2160 JPEG')
            ->assertSee('Logo band (auto)')
            ->assertSee('Download all as ZIP')
            ->assertSee('← Previous')
            ->assertSee('Next →')
            ->assertSee('Junior Showcase')
            ->assertDontSee('Centre Stage Dance \\u0026 Entertainment - DEMO', false);

        $this->patch(route('admin.concert-title-cards.update', $event), [
            'logo_source' => 'studio', 'background_color' => '#000000', 'logo_band_color' => '#123456',
            'text_color' => '#FFFFFF', 'separator_start_color' => '#000000', 'separator_end_color' => '#FFFFFF',
        ])->assertRedirect();

        $template = ConcertTitleCardTemplate::firstOrFail();
        $this->assertSame('#123456', $template->logo_band_color);
        $this->assertSame('#000000', $template->separator_start_color);
        $this->assertSame('Atlanta', $template->font_family);
        $this->assertSame($staff->id, $template->updated_by_user_id);
        $tracker = app(ConcertTracker::class);
        $this->assertSame('not_started', $tracker->detail($event->fresh(), 'video_titles')['status']['key']);
        $this->assertSame($url, $tracker->detail($event->fresh(), 'video_titles')['status']['detailUrl']);
        $this->patchJson(route('admin.concert-tracker.source-bar.update', [$event, 'video_titles']), ['panel' => 'status', 'status_override' => 'complete'])->assertUnprocessable();
        $other = SchedulingEvent::create(['name' => 'Another concert', 'event_type' => 'concert', 'event_date' => '2026-10-01']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'video-titles', 'event' => $other->uuid]))->assertNotFound();
        $this->get(route('admin.concert-title-cards.show', $other))->assertOk()->assertSee('2.3 Video titles');
        $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
    }

    public function test_competitions_do_not_have_concert_title_cards(): void
    {
        $staff = User::factory()->staff()->create();
        $event = SchedulingEvent::create([
            'name' => 'Competition', 'event_type' => 'competition', 'event_date' => '2026-09-24',
        ]);

        $this->actingAs($staff)->get(route('admin.concert-title-cards.show', $event))->assertNotFound();
    }
}
