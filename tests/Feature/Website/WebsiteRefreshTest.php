<?php

namespace Tests\Feature\Website;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Website\Models\WebsitePage;
use App\Features\Website\Services\WebsiteContentRefresh;
use App\Features\Website\Services\WebsiteDefaults;
use App\Features\Website\Services\WebsiteEvents;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_month_window_uses_start_and_finish_dates_and_does_not_truncate_events(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->startOfDay());
        $ongoing = $this->event('In progress', '2026-09-26');
        $ongoing->shifts()->create(['period' => 'morning', 'shift_date' => '2026-09-30']);
        $shiftOnly = $this->event('Shift range around event date', '2026-10-05');
        $shiftOnly->shifts()->create(['period' => 'morning', 'shift_date' => '2026-10-04']);
        $shiftOnly->shifts()->create(['period' => 'afternoon', 'shift_date' => '2026-10-06']);
        $this->event('Boundary event', '2027-03-28');
        $this->event('Too far away', '2027-03-29');
        $this->event('Finished yesterday', '2026-09-27');
        $cancelled = $this->event('Cancelled', '2026-10-01');
        $cancelled->update(['cancelled_at' => now()]);
        foreach (range(1, 25) as $i) {
            $this->event('Extra event '.$i, '2026-10-10');
        }
        $data = app(WebsiteEvents::class)->homepage()['competitions'];
        $this->assertCount(28, $data);
        $this->assertSame('In progress', $data->first()['event']->name);
        $this->assertSame('2026-09-26', $data->first()['start']->toDateString());
        $this->assertSame('2026-09-30', $data->first()['end']->toDateString());
        $response = $this->get('/')->assertOk()->assertSee('Upcoming Competition Events')->assertSee('28 Mar 2027')
            ->assertSee('4 Oct 2026')->assertSee('6 Oct 2026')->assertDontSee('Too far away')->assertDontSee('Finished yesterday')->assertDontSee('Cancelled');
        $response->assertDontSee('Upcoming Concert Events');
    }

    public function test_manual_hide_survives_date_changes_and_restore_respects_the_window(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        $event = $this->event('Manually hidden competition', '2026-10-01');
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.website.events.update', $event), ['website_visible' => false])->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee($event->name);
        $event->update(['event_date' => '2026-12-01']);
        $this->get('/')->assertDontSee($event->name);
        $this->patch(route('admin.website.events.update', $event), ['website_visible' => true])->assertSessionHasNoErrors();
        $this->get('/')->assertSee($event->name);
        $event->update(['event_date' => '2027-04-01']);
        $this->get('/')->assertDontSee($event->name);
        $this->travelTo(now()->setDate(2026, 10, 2));
        $this->get('/')->assertSee($event->name);
    }

    public function test_concerts_are_deduplicated_logo_tiles_with_no_event_details_and_hide_when_empty(): void
    {
        $studio = Studio::factory()->create(['name' => 'Logo Studio', 'logo_path' => 'website-test/studio.png']);
        $first = $this->event('Private concert title one', today()->addDay()->toDateString(), 'concert', $studio);
        $second = $this->event('Private concert title two', today()->addDays(2)->toDateString(), 'concert', $studio);
        $other = Studio::factory()->create(['name' => 'No logo studio']);
        $this->event('No logo event', today()->addDays(3)->toDateString(), 'concert', $other);
        $response = $this->get('/')->assertOk()->assertSee('Upcoming Concert Events')->assertSee('website-test/studio.png')
            ->assertSee('Logo coming soon')->assertDontSee('Private concert title one')->assertDontSee('Private concert title two');
        $this->assertSame(1, substr_count($response->getContent(), 'data-upcoming-studio="'.$studio->uuid.'"'));
        $this->actingAs(User::factory()->admin()->create());
        foreach (SchedulingEvent::all() as $event) {
            $this->patch(route('admin.website.events.update', $event), ['website_visible' => false])->assertSessionHasNoErrors();
        }
        $this->get('/')->assertDontSee('Upcoming Concert Events');
    }

    public function test_full_service_content_and_placeholder_bios_are_editable_without_creating_crew(): void
    {
        $this->assertSame(0, CrewProfile::count());
        foreach (['book', 'competition', 'portfolio', 'pose', 'shootme', 'promo', 'studio', 'shop', 'terms', 'privacy'] as $slug) {
            $this->get(route('website.detail', $slug))->assertOk();
        }
        $this->get('/book')->assertSee('$800')->assertSee('$600')->assertSee('$4,500')->assertSee('XLR')
            ->assertSee('48 hours')->assertSee('Family sharing')->assertDontSee('forms.gle')->assertDontSee('WeTransfer');
        $this->get('/about')->assertSee('Western Australian Academy')->assertSee('Placeholder biography');
        $this->get('/pose')->assertDontSee('9th of March 2025');
        $this->get('/shootme')->assertDontSee('15th October 2023');
        $this->get('/help')->assertSee('My order has not arrived')->assertSee('Australian Consumer Law');
        $page = WebsitePage::where('slug', 'about')->firstOrFail();
        $input = [...$page->draft, 'action' => 'publish'];
        $input['sections'] = array_map(fn ($s) => ($s['kind'] ?? '') === 'crew_placeholder' ? [...$s, 'visible' => false] : $s, $input['sections']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.website.pages.update', $page), $input)->assertSessionHasNoErrors();
        $this->get('/about')->assertDontSee('Placeholder biography');
    }

    public function test_content_refresh_and_rollback_preserve_custom_edits_and_are_idempotent(): void
    {
        $service = app(WebsiteContentRefresh::class);
        $page = WebsitePage::where('slug', 'home')->firstOrFail();
        $original = WebsiteDefaults::pages()['home'];
        $page->update(['draft' => [...$original, 'heading' => 'Custom draft'], 'published' => null]);
        $before = WebsitePage::count();
        $service->apply();
        $service->apply();
        $this->assertSame($before, WebsitePage::count());
        $this->assertSame('Custom draft', $page->fresh()->draft['heading']);
        $this->assertNull($page->fresh()->published);
        $service->revert();
        $this->assertSame('Custom draft', $page->fresh()->draft['heading']);
        $this->assertNull($page->fresh()->published);
        $this->assertNull(WebsitePage::where('slug', 'book')->first());
    }

    private function event(string $name, ?string $date, string $type = 'competition', ?Studio $studio = null): SchedulingEvent
    {
        return SchedulingEvent::create(['name' => $name, 'event_type' => $type, 'event_date' => $date, 'studio_id' => $studio?->id]);
    }
}
