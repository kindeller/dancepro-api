<?php

namespace Tests\Feature\Crew;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_directory_has_tabbed_crew_competition_and_studio_contacts(): void
    {
        $viewer = User::factory()->crew()->create();
        CrewProfile::factory()->for($viewer)->create(['preferred_name' => 'Viewer']);
        $colleague = User::factory()->crew()->create(['name' => 'Alex Reed']);
        $colleagueProfile = CrewProfile::factory()->for($colleague)->create(['preferred_name' => 'Alex', 'phone' => '0400 200 001']);
        $competition = CompetitionContact::query()->create([
            'name' => 'Perth Dance Festival',
            'code' => 'PDF',
            'logo_path' => 'logos/events/perth-dance-festival/logo.jpg',
            'organiser_name' => 'Kirsty Bufton',
            'organiser_email' => 'kirsty@example.com',
            'organiser_phone' => '0412 345 678',
            'is_active' => true,
        ]);
        $studio = Studio::factory()->create([
            'name' => 'Encore Dance Academy',
            'logo_path' => 'logos/studios/encore/logo.jpg',
            'contact_name' => 'Nicole Director',
            'contact_phone' => '0433 555 111',
        ]);

        $this->actingAs($viewer)->get(route('crew.directory.index'))
            ->assertOk()->assertSee('Alex')->assertSee('tel:0400200001', false)->assertSee('Chat');
        $this->actingAs($viewer)->get(route('crew.directory.index', ['view' => 'competitions']))
            ->assertOk()->assertSee('Perth Dance Festival')->assertSee('PDF')->assertSee('Kirsty Bufton')->assertSee('tel:0412345678', false)
            ->assertSee('directory-logo', false)->assertSee($competition->logoUrl(), false);
        $this->actingAs($viewer)->get(route('crew.directory.index', ['view' => 'studios']))
            ->assertOk()->assertSee('Encore Dance Academy')->assertSee($studio->code)->assertSee('Nicole Director')->assertSee('tel:0433555111', false)
            ->assertSee('directory-logo', false)->assertSee($studio->logoUrl(), false);
        $this->actingAs($viewer)->post(route('crew.chat.start'), ['recipient_profile_uuid' => $colleagueProfile->uuid])
            ->assertRedirect();
    }

    public function test_inactive_crew_are_hidden_from_the_directory(): void
    {
        $viewer = User::factory()->crew()->create();
        CrewProfile::factory()->for($viewer)->create();
        $inactive = User::factory()->crew()->inactive()->create(['name' => 'Hidden Person']);
        CrewProfile::factory()->for($inactive)->create(['preferred_name' => 'Hidden Person']);

        $this->actingAs($viewer)->get(route('crew.directory.index'))->assertOk()->assertDontSee('Hidden Person');
    }

    public function test_inactive_studios_and_competitions_are_hidden_from_the_directory(): void
    {
        $viewer = User::factory()->crew()->create();
        CrewProfile::factory()->for($viewer)->create();
        Studio::factory()->create(['name' => 'Visible Studio', 'status' => StudioStatus::Active]);
        Studio::factory()->inactive()->create(['name' => 'Hidden Studio']);
        CompetitionContact::query()->create([
            'name' => 'Visible Competition',
            'organiser_name' => 'Active Organiser',
            'organiser_email' => 'active@example.com',
            'organiser_phone' => '',
            'is_active' => true,
        ]);
        CompetitionContact::query()->create([
            'name' => 'Hidden Competition',
            'organiser_name' => 'Inactive Organiser',
            'organiser_email' => 'inactive@example.com',
            'organiser_phone' => '',
            'is_active' => false,
        ]);

        $this->actingAs($viewer)->get(route('crew.directory.index', ['view' => 'studios']))
            ->assertOk()->assertSee('Visible Studio')->assertDontSee('Hidden Studio');
        $this->actingAs($viewer)->get(route('crew.directory.index', ['view' => 'competitions']))
            ->assertOk()->assertSee('Visible Competition')->assertDontSee('Hidden Competition');
    }
}
