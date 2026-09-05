<?php

namespace Tests\Feature\Crew;

use App\Features\Auth\Support\TokenAbility;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->assertJsonPath('data.next_assignment.event_name', 'Own upcoming event')
            ->assertJsonStructure(['data' => [
                'pending_acknowledgements', 'pending_cover_requests', 'pending_timesheets',
            ]]);

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

    public function test_cancelled_assignments_are_read_only_and_available_in_cancelled_history(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile, 'Cancelled mobile event', today()->addDay()->toDateString());
        $assignment->shift->schedulingEvent->update(['cancelled_at' => now()]);

        $this->getJson('/api/v1/assignments')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/assignments?scope=cancelled')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->uuid)
            ->assertJsonPath('data.0.cancelled', true)
            ->assertJsonPath('data.0.allowed_actions', []);
        $this->getJson('/api/v1/assignments?scope=all')
            ->assertOk()->assertJsonPath('data.0.id', $assignment->uuid);
        $this->getJson('/api/v1/assignments/'.$assignment->uuid)
            ->assertOk()->assertJsonPath('data.cancelled', true);
    }

    public function test_assignment_detail_contains_private_venue_programme_and_applicable_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['operations.filesystem_disk' => 'local', 'uploads.public_disk' => 'public']);
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile, 'Detailed Event', today()->addDay()->toDateString());
        $assignment->update([
            'crew_role_id' => CrewRole::query()->where('code', 'competition-photographer-p1')->firstOrFail()->id,
        ]);
        $eventType = EventTypeDefinition::query()->create([
            'code' => 'dance-competition', 'name' => 'Dance Competition',
            'system_category' => 'competition', 'is_active' => true,
        ]);
        $venue = Venue::query()->create([
            'name' => 'Perth Theatre', 'address_line_1' => '1 Stage Road', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'parking_notes' => 'Use bay 4.',
            'access_notes' => 'Enter backstage.', 'operational_notes' => 'Call on arrival.',
            'map_path' => 'venues/map.pdf', 'reference_image_path' => 'venues/reference.jpg',
        ]);
        Storage::disk('local')->put('venues/map.pdf', 'map');
        Storage::disk('local')->put('events/programme.pdf', 'programme');
        Storage::disk('local')->put('resources/checklist.pdf', 'checklist');
        Storage::disk('public')->put('venues/reference.jpg', 'image');
        $assignment->shift->schedulingEvent->update([
            'venue_id' => $venue->id,
            'programme_path' => 'events/programme.pdf',
            'event_type_definition_id' => $eventType->id,
        ]);
        OperationalResource::query()->create([
            'title' => 'Photographer checklist', 'resource_type' => 'document',
            'event_type_definition_id' => $eventType->id, 'role_code' => 'competition-photographer',
            'file_path' => 'resources/checklist.pdf', 'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/assignments/'.$assignment->uuid)
            ->assertOk()
            ->assertJsonPath('data.venue.parking_notes', 'Use bay 4.')
            ->assertJsonPath('data.venue.access_notes', 'Enter backstage.')
            ->assertJsonPath('data.venue.operational_notes', 'Call on arrival.')
            ->assertJsonPath('data.documents.0.title', 'Photographer checklist');

        $temporaryUrls = [];
        foreach (['data.programme_url', 'data.venue.map_url', 'data.venue.reference_image_url'] as $path) {
            $issued = $this->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson($response->json($path))
                ->assertOk()
                ->assertHeader('cache-control', 'no-store, private')
                ->assertJsonStructure(['data' => ['url', 'expires_at', 'mime_type', 'filename']]);
            $temporaryUrls[] = $issued->json('data.url');
            $this->get($issued->json('data.url'))->assertOk()->assertHeader('cache-control', 'no-store, private');
        }
        $assignment->shift->schedulingEvent->update(['cancelled_at' => now()]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/files/programme/download')
            ->assertOk();
        $this->get('/api/v1/assignment-files/'.$assignment->uuid.'/programme/content')->assertForbidden();
        $this->travel(11)->minutes();
        $this->get($temporaryUrls[0])->assertForbidden();
        $this->travelBack();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$this->assignmentFor($this->completeCrew(), 'Private files', today()->addDay()->toDateString())->uuid.'/files/programme/download')
            ->assertNotFound();
    }

    public function test_address_search_is_authenticated_and_restricts_provider_results_to_australia(): void
    {
        Http::fake(fn ($request) => str_contains($request->url(), '/place/details/')
            ? Http::response([
                'status' => 'OK',
                'result' => ['address_components' => [
                    ['long_name' => '1', 'short_name' => '1', 'types' => ['street_number']],
                    ['long_name' => 'Test Street', 'short_name' => 'Test St', 'types' => ['route']],
                    ['long_name' => 'Perth', 'short_name' => 'Perth', 'types' => ['locality']],
                    ['long_name' => 'Western Australia', 'short_name' => 'WA', 'types' => ['administrative_area_level_1']],
                    ['long_name' => '6000', 'short_name' => '6000', 'types' => ['postal_code']],
                    ['long_name' => 'Australia', 'short_name' => 'AU', 'types' => ['country']],
                ]],
            ])
            : Http::response([
                'status' => 'OK',
                'predictions' => [['place_id' => 'place-1', 'description' => '1 Test Street, Perth WA, Australia']],
            ]));
        config(['services.google_maps.server_key' => 'server-secret']);
        $this->authenticatedCrew();

        $this->getJson('/api/v1/address-search?q=1%20Test%20Street')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'place-1')
            ->assertJsonPath('data.0.label', '1 Test Street, Perth WA, Australia');
        $this->getJson('/api/v1/address-search/place-1')
            ->assertOk()
            ->assertJsonPath('data.label', '1 Test Street, Perth WA 6000')
            ->assertJsonPath('data.line_1', '1 Test Street')
            ->assertJsonPath('data.line_2', null)
            ->assertJsonPath('data.suburb', 'Perth')
            ->assertJsonPath('data.state', 'WA')
            ->assertJsonPath('data.postcode', '6000');
        Http::assertSent(fn ($request): bool => ($request->data()['components'] ?? null) === 'country:au'
            && ($request->data()['key'] ?? null) === 'server-secret');
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
        $role = CrewRole::query()->create([
            'code' => 'profile-badge-role', 'name' => 'Profile Badge Role',
            'event_type' => 'competition', 'is_active' => true,
        ]);
        $profile->roles()->attach($role->id, ['status' => 'approved']);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $profile->uuid)
            ->assertJsonPath('data.payment_details.bsb_last_four', '3456')
            ->assertJsonPath('data.payment_details.account_number_last_four', '6789')
            ->assertJsonPath('data.badges.training.0.name', 'Profile Badge Role')
            ->assertDontSee('123-456')
            ->assertDontSee('123456789')
            ->assertDontSee('Administrator only')
            ->assertDontSee('SUPER-SECRET');
    }

    public function test_current_user_capabilities_identify_admin_crew_without_granting_it_to_regular_crew(): void
    {
        [$crewUser] = $this->authenticatedCrew();
        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.capabilities', ['crew']);

        $admin = User::factory()->crew()->create(['is_admin' => true]);
        $this->completeCrew(user: $admin);
        Sanctum::actingAs($admin, [TokenAbility::CrewMobile->value]);

        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.capabilities', ['crew', 'admin']);
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
