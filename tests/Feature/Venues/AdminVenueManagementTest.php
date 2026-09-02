<?php

namespace Tests\Feature\Venues;

use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminVenueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_search_and_create_venues_on_the_management_page(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $regal = Venue::query()->create(['name' => 'Regal Theatre', 'suburb' => 'Subiaco', 'map_path' => 'venues/regal.jpg']);
        Venue::query()->create(['name' => 'Quarry Amphitheatre', 'suburb' => 'City Beach']);
        foreach (range(1, 24) as $number) {
            Venue::query()->create(['name' => sprintf('Test Venue %02d', $number)]);
        }

        $this->actingAs($staff)->get(route('admin.venues.index'))
            ->assertOk()
            ->assertSee('Venue Management')
            ->assertSee('Regal Theatre')
            ->assertSee('Expand map for Regal Theatre')
            ->assertSee($regal->mapUrl(), false)
            ->assertSee('Quarry Amphitheatre')
            ->assertSee('Showing 1 to 25 of 26 results')
            ->assertSee('?page=2', false)
            ->assertDontSee('<svg', false);

        $this->actingAs($staff)->get(route('admin.venues.index', ['search' => 'Subiaco']))
            ->assertOk()
            ->assertSee('Regal Theatre')
            ->assertDontSee('Quarry Amphitheatre');

        $this->actingAs($staff)->post(route('admin.venues.store'), [
            'name' => 'New Theatre',
            'suburb' => 'Perth',
            'state' => 'WA',
            'operational_notes' => 'Tiered seating.',
        ])->assertRedirect(route('admin.venues.index'));

        $this->assertDatabaseHas('venues', ['name' => 'New Theatre', 'operational_notes' => 'Tiered seating.']);
    }

    public function test_customers_cannot_access_venue_management(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.venues.index'))->assertForbidden();
    }
}
