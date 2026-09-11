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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrewMobileReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_assignment_moves_on_after_finish_time_or_clock_out(): void
    {
        $this->travelTo(Carbon::parse('2026-09-11 19:59:00'));
        [, $profile] = $this->authenticatedCrew();
        $today = $this->assignmentFor($profile, 'Today shift', '2026-09-11');
        $today->shift->update(['estimated_finish_at' => '2026-09-11 18:00:00']);
        $tomorrow = $this->assignmentFor($profile, 'Tomorrow shift', '2026-09-12');
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.next_assignment.id', $today->uuid);
        $this->travelTo(Carbon::parse('2026-09-11 20:00:00'));
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.next_assignment.id', $tomorrow->uuid);
        $this->travelTo(Carbon::parse('2026-09-11 17:00:00'));
        $today->timeEntry()->create(['actual_clock_in_at' => '2026-09-11 13:00:00', 'actual_finish_at' => '2026-09-11 16:30:00']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.next_assignment.id', $tomorrow->uuid);
        $tomorrow->update(['status' => 'draft']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.next_assignment', null);
    }

    public function test_timesheet_badge_waits_until_competition_work_is_actionable(): void
    {
        $this->travelTo(Carbon::parse('2026-09-11 20:00:00'));
        [$user, $profile] = $this->authenticatedCrew();
        $first = $this->assignmentFor($profile, 'Two day competition', '2026-09-11');
        $first->shift->update(['estimated_finish_at' => '2026-09-11 18:00:00']);
        $first->timeEntry()->create(['actual_clock_in_at' => '2026-09-11 13:00:00', 'actual_finish_at' => '2026-09-11 18:00:00']);
        $nextShift = $first->shift->schedulingEvent->shifts()->create(['shift_date' => '2026-09-12', 'estimated_finish_at' => '2026-09-12 18:00:00']);
        $second = $nextShift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $first->crew_role_id, 'status' => 'published']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pending_timesheets', 0);
        $this->travelTo(Carbon::parse('2026-09-12 17:00:00'));
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pending_timesheets', 0);
        $this->travelTo(Carbon::parse('2026-09-12 19:00:00'));
        // Only the missing times need attention; the first shift is still waiting.
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pending_timesheets', 1);
        $second->timeEntry()->create(['actual_clock_in_at' => '2026-09-12 13:00:00', 'actual_finish_at' => '2026-09-12 18:00:00']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pending_timesheets', 2);
        $first->shift->schedulingEvent->update(['cancelled_at' => now()]);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pending_timesheets', 0);
    }

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

    #[DataProvider('artworkCases')]
    public function test_mobile_cards_share_public_artwork_selection(
        string $eventType,
        ?string $organisationLogo,
        ?string $eventLogo,
        ?string $expectedLogo,
        bool $hasOrganisation = true,
        string $publicBase = '/storage',
    ): void {
        config(['uploads.public_disk' => 'public']);
        Storage::fake('public', ['url' => $publicBase]);
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile, 'Artwork fixture', today()->toDateString());
        // Give the unrelated organisation artwork too, to catch cross-type selection.
        $studio = Studio::factory()->create([
            'logo_path' => $eventType === 'concert' ? $organisationLogo : 'studios/unrelated.png',
        ]);
        $competition = CompetitionContact::query()->create([
            'name' => 'Artwork competition',
            'organiser_name' => 'Fixture organiser', 'organiser_email' => 'artwork@example.com', 'organiser_phone' => '0400 000 000',
            'logo_path' => $eventType === 'competition' ? $organisationLogo : 'competitions/unrelated.png',
        ]);
        $event = $assignment->shift->schedulingEvent;
        $event->update([
            'event_type' => $eventType,
            'studio_id' => $hasOrganisation ? $studio->id : null,
            'competition_contact_id' => $hasOrganisation ? $competition->id : null,
            'logo_path' => $eventLogo,
            'availability_status' => 'open',
            'availability_deadline' => now()->addDay(),
        ]);
        $event->shifts()->create(['shift_date' => today()->toDateString()]);
        $expectedUrl = $expectedLogo === null ? null : url($publicBase.'/'.$expectedLogo);

        foreach ([
            '/api/v1/assignments' => 'data.0',
            '/api/v1/assignments/'.$assignment->uuid => 'data',
            '/api/v1/dashboard' => 'data.next_assignment',
            '/api/v1/availability' => 'data.0',
            '/api/v1/timesheets' => 'data.0',
        ] as $endpoint => $path) {
            $this->getJson($endpoint)->assertOk()
                ->assertJsonPath($path.'.event_logo_url', $expectedUrl)
                ->assertJsonPath($path.'.event_type', $eventType);
        }

        // Desktop event artwork keeps using the event's own logo.
        $this->assertSame($eventLogo === null ? null : $publicBase.'/'.$eventLogo, $event->logoUrl());
    }

    public static function artworkCases(): array
    {
        $cases = [];
        foreach (['concert', 'competition'] as $type) {
            $cases[$type.' organisation preferred'] = [$type, 'organisations/logo.png', 'events/logo.png', 'organisations/logo.png'];
            $cases[$type.' event fallback'] = [$type, null, 'events/logo.png', 'events/logo.png'];
            $cases[$type.' no artwork'] = [$type, null, null, null];
            $cases[$type.' no organisation fallback'] = [$type, null, 'events/logo.png', 'events/logo.png', false];
            $cases[$type.' no organisation or artwork'] = [$type, null, null, null, false];
            $cases[$type.' public CDN'] = [$type, 'organisations/logo.png', null, 'organisations/logo.png', true, 'https://artwork.example.com'];
        }

        return $cases;
    }

    public function test_mobile_artwork_relationship_queries_are_batched(): void
    {
        [, $profile] = $this->authenticatedCrew();
        foreach (['concert', 'competition', 'concert', 'competition'] as $index => $type) {
            $assignment = $this->assignmentFor($profile, 'Artwork '.$index, today()->toDateString());
            $studio = Studio::factory()->create(['logo_path' => 'studios/'.$index.'.png']);
            $competition = CompetitionContact::query()->create([
                'name' => 'Competition '.$index, 'logo_path' => 'competitions/'.$index.'.png',
                'organiser_name' => 'Fixture organiser', 'organiser_email' => 'artwork@example.com', 'organiser_phone' => '0400 000 000',
            ]);
            $assignment->shift->schedulingEvent->update([
                'event_type' => $type, 'studio_id' => $studio->id, 'competition_contact_id' => $competition->id,
                'availability_status' => 'open', 'availability_deadline' => now()->addDay(),
            ]);
            $assignment->shift->schedulingEvent->shifts()->create(['shift_date' => today()->toDateString()]);
        }

        DB::enableQueryLog();
        try {
            foreach (['assignments', 'dashboard', 'availability', 'timesheets'] as $endpoint) {
                DB::flushQueryLog();
                $this->getJson('/api/v1/'.$endpoint)->assertOk();
                $artworkQueries = collect(DB::getQueryLog())->filter(
                    fn (array $query): bool => preg_match('/from [`"]?(studios|competition_contacts)[`"]? /i', $query['query']) === 1,
                );
                $this->assertCount(2, $artworkQueries, $endpoint.' must batch studio and competition contact loads.');
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
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
