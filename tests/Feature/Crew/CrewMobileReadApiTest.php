<?php

namespace Tests\Feature\Crew;

use App\Features\Auth\Support\TokenAbility;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_dashboard_and_assignment_list_only_return_the_current_crews_published_work(): void
    {
        [$user, $profile] = $this->authenticatedCrew();
        $own = $this->assignmentFor($profile, 'Own upcoming event', today()->addDay()->toDateString());
        $other = $this->completeCrew();
        $this->assignmentFor($other, 'Someone else event', today()->addDay()->toDateString());
        $this->assignmentFor($profile, 'Past event', today()->subDay()->toDateString());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.next_assignment.id', $own->uuid)
            ->assertJsonPath('data.next_assignment.event_name', 'Own upcoming event');

        $this->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->uuid)
            ->assertJsonMissing(['event_name' => 'Someone else event'])
            ->assertJsonStructure(['meta' => ['next_cursor', 'has_more']]);
    }

    public function test_assignment_detail_uses_uuid_and_hides_other_crews_assignments(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $own = $this->assignmentFor($profile, 'My Event', today()->addDay()->toDateString());
        $other = $this->assignmentFor($this->completeCrew(), 'Private Event', today()->addDay()->toDateString());

        $this->getJson('/api/v1/assignments/'.$own->uuid)
            ->assertOk()
            ->assertJsonPath('data.id', $own->uuid)
            ->assertJsonPath('data.event_name', 'My Event');
        $this->getJson('/api/v1/assignments/'.$other->uuid)->assertNotFound();
        $this->getJson('/api/v1/assignments/'.$other->id)->assertNotFound();
    }

    public function test_directory_contains_only_active_records_and_no_admin_notes(): void
    {
        $this->authenticatedCrew();
        $visibleCrew = $this->completeCrew('Visible Crew');
        $hiddenUser = User::factory()->crew()->inactive()->create();
        CrewProfile::factory()->for($hiddenUser)->create(['preferred_name' => 'Hidden Crew']);
        $studio = Studio::factory()->create(['name' => 'Active Studio', 'code' => 'AS', 'status' => StudioStatus::Active, 'notes' => 'Private note']);
        $studio->contacts()->create(['name' => 'Studio Contact', 'role' => 'Owner', 'emails' => ['studio@example.com'], 'phone' => '0400 100 100']);
        Studio::factory()->create(['name' => 'Inactive Studio', 'status' => StudioStatus::Inactive]);
        $competition = CompetitionContact::query()->create([
            'name' => 'Active Competition', 'code' => 'AC', 'is_active' => true,
            'organiser_name' => 'Organiser', 'organiser_email' => 'organiser@example.com',
            'organiser_phone' => '0400 300 300', 'notes' => 'Private competition note',
        ]);
        $competition->staff()->create(['name' => 'Competition Contact', 'role' => 'Director', 'emails' => ['competition@example.com'], 'phone' => '0400 200 200']);
        CompetitionContact::query()->create([
            'name' => 'Inactive Competition', 'is_active' => false,
            'organiser_name' => 'Inactive', 'organiser_email' => 'inactive@example.com',
            'organiser_phone' => '0400 400 400',
        ]);

        $this->getJson('/api/v1/directory')
            ->assertOk()
            ->assertJsonFragment(['id' => $visibleCrew->uuid, 'name' => 'Visible Crew'])
            ->assertJsonFragment(['name' => 'Active Studio'])
            ->assertJsonFragment(['emails' => ['studio@example.com']])
            ->assertJsonFragment(['name' => 'Active Competition'])
            ->assertJsonMissing(['name' => 'Hidden Crew'])
            ->assertJsonMissing(['name' => 'Inactive Studio'])
            ->assertJsonMissing(['name' => 'Inactive Competition'])
            ->assertDontSee('Private note')
            ->assertDontSee('Private competition note');
    }

    public function test_profile_returns_redacted_payment_identifiers_and_not_private_admin_fields(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $profile->update([
            'bank_account_name' => 'Morgan Vale', 'bank_name' => 'Example Bank',
            'bank_bsb' => '123-456', 'bank_account_number' => '123456789',
            'internal_notes' => 'Administrator only', 'super_member_number' => 'SUPER-SECRET',
        ]);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $profile->uuid)
            ->assertJsonPath('data.payment_details.bsb_last_four', '3456')
            ->assertJsonPath('data.payment_details.account_number_last_four', '6789')
            ->assertDontSee('123-456')
            ->assertDontSee('123456789')
            ->assertDontSee('Administrator only')
            ->assertDontSee('SUPER-SECRET');
    }

    public function test_incomplete_crew_can_read_profile_but_not_operational_data(): void
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => null]);
        CrewProfile::factory()->for($user)->create([
            'phone' => null, 'address_line_1' => null, 'suburb' => null, 'state' => null,
            'postcode' => null, 'working_with_children_number' => null, 'working_with_children_expiry' => null,
        ]);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        $this->getJson('/api/v1/profile')->assertOk();
        $this->getJson('/api/v1/dashboard')->assertForbidden()
            ->assertJsonPath('errors.onboarding.0', 'required');
        $this->getJson('/api/v1/assignments')->assertForbidden();
        $this->getJson('/api/v1/directory')->assertForbidden();
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(): array
    {
        $user = User::factory()->crew()->create();
        $profile = $this->completeCrew(user: $user);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    private function completeCrew(string $name = 'Crew Member', ?User $user = null): CrewProfile
    {
        $user ??= User::factory()->crew()->create();

        return CrewProfile::factory()->for($user)->create([
            'preferred_name' => $name, 'phone' => '0400 000 000', 'address_line_1' => '1 Test Street',
            'suburb' => 'Perth', 'state' => 'WA', 'postcode' => '6000',
            'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear()->toDateString(),
        ]);
    }

    private function assignmentFor(CrewProfile $profile, string $name, string $date)
    {
        $role = CrewRole::query()->firstOrCreate(['code' => 'mobile-photographer'], [
            'name' => 'Photographer', 'event_type' => 'competition', 'is_active' => true,
        ]);
        $event = SchedulingEvent::query()->create(['name' => $name, 'event_type' => 'competition', 'event_date' => $date]);
        $shift = $event->shifts()->create(['shift_date' => $date]);

        return $shift->assignments()->create([
            'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
            'status' => 'published', 'published_at' => now(),
        ]);
    }
}
