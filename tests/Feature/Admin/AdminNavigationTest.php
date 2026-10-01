<?php

namespace Tests\Feature\Admin;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_groups_hub_management_and_media_links(): void
    {
        $staff = User::factory()->staff()->create();
        CrewProfile::factory()->for($staff)->create();

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('My Hub')
            ->assertSeeInOrder([
                'href="'.route('crew.availability.index').'"',
                'images/brand/dancepro-crew-hub-icon.png',
                '<span>My Hub</span>',
                'href="'.route('customer.media.index').'"',
                'images/brand/dancepro-media-icon.png',
                '<span>My Media</span>',
            ], false)
            ->assertSee('Hub management')
            ->assertSee(route('admin.hub.dashboard'), false)
            ->assertSee('Exceptions')
            ->assertSee('Event management', false)
            ->assertSee('Venue management')
            ->assertSee('Crew management')
            ->assertSee('Crew payments')
            ->assertSee('Concert tracker')
            ->assertSee(route('admin.concert-tracker.index'), false)
            ->assertDontSee('>Payment settings</a>', false)
            ->assertSee('Media')
            ->assertSee('Media dashboard')
            ->assertSee('Concert media')
            ->assertSee('Concert photos')
            ->assertDontSee('>Concert Photo Releases</a>', false)
            ->assertSee('Competition media')
            ->assertDontSee('>Competition Videos</a>', false)
            ->assertSee('Download links');
    }

    public function test_admin_layout_has_an_accessible_mobile_navigation_toggle(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="mobile-nav-toggle"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-controls="admin-navigation"', false)
            ->assertSee('id="admin-navigation"', false)
            ->assertSee('data-dancepro-foundation', false)
            ->assertSee("event.key === 'Escape'", false)
            ->assertSee("window.matchMedia('(min-width: 901px)')", false);
    }

    public function test_admin_layout_provides_a_clipboard_fallback(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.studios.index'))
            ->assertOk()
            ->assertSee('navigator.clipboard?.writeText', false)
            ->assertSee("document.execCommand('copy')", false);
    }

    public function test_event_management_pages_share_event_tabs(): void
    {
        $staff = User::factory()->staff()->create();

        foreach ([
            route('admin.concert-bookings.index'),
            route('admin.event-management.pending'),
            route('admin.scheduling-events.index'),
            route('admin.event-types.index'),
            route('admin.event-management.checklists'),
        ] as $url) {
            $this->actingAs($staff)->get($url)
                ->assertOk()
                ->assertSee('Event bookings')
                ->assertSee('Pending events')
                ->assertSee('Event availability')
                ->assertSee('Event types')
                ->assertSee('Pre-start checks');
        }

        $this->actingAs($staff)->get(route('admin.concert-bookings.index'))
            ->assertSee('Add event')
            ->assertSee(route('admin.scheduling-events.create'), false);
    }

    public function test_hub_dashboard_links_directly_to_full_screen_event_availability(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.hub.dashboard'))
            ->assertOk()
            ->assertSee('Event availability')
            ->assertSee(route('admin.scheduling-events.index', ['fullscreen' => 1]), false);

        $this->actingAs($staff)->get(route('admin.scheduling-events.index', ['fullscreen' => 1]))
            ->assertOk()
            ->assertSee('forced-fullscreen', false)
            ->assertSee('Exit full screen');
    }

    public function test_crew_management_pages_share_hr_tabs(): void
    {
        $staff = User::factory()->staff()->create();

        foreach ([
            route('admin.crew.index'),
            route('admin.crew-roles.index'),
            route('admin.crew-contracts.index'),
            route('admin.crew-management.recognitions-rewards'),
            route('admin.crew-management.training'),
            route('admin.crew-management.resources'),
        ] as $url) {
            $this->actingAs($staff)->get($url)
                ->assertOk()
                ->assertSee('Crew')
                ->assertSee('Crew roles')
                ->assertSee('Crew contracts')
                ->assertSee('Recognitions &amp; rewards', false)
                ->assertSee('Crew training')
                ->assertSee('Crew resources');
        }
    }

    public function test_crew_payments_pages_share_timesheets_invoices_and_settings_tabs(): void
    {
        $staff = User::factory()->staff()->create();

        foreach ([route('admin.timesheets.index'), route('admin.timesheets.invoices.index'), route('admin.payments.index')] as $url) {
            $this->actingAs($staff)->get($url)
                ->assertOk()
                ->assertSee('Crew payments')
                ->assertSee('Timesheets')
                ->assertSee('Invoices')
                ->assertSee('Payment settings');
        }
    }

    public function test_admin_can_access_the_personal_crew_hub(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Morgan Vale']);
        CrewProfile::factory()->for($staff)->create();

        $this->actingAs($staff)->get(route('crew.availability.index'))
            ->assertOk()
            ->assertSee('data-dancepro-foundation', false)
            ->assertSee('Admin Hub')
            ->assertSee('href="'.route('admin.dashboard').'"', false);
    }
}
