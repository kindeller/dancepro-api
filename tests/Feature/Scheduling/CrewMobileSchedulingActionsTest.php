<?php

namespace Tests\Feature\Scheduling;

use App\Features\Auth\Support\TokenAbility;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileSchedulingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_event_code_is_consistent_across_dashboard_assignments_availability_and_timesheets(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile);
        $competition = CompetitionContact::query()->create([
            'name' => 'Express Dance Challenge', 'code' => 'EDC',
            'organiser_name' => 'Example', 'organiser_email' => 'example@example.test', 'organiser_phone' => '0000 000 000',
        ]);
        $event = $assignment->shift->schedulingEvent;
        $event->update([
            'competition_contact_id' => $competition->id, 'event_date' => today(),
            'availability_status' => 'open', 'availability_deadline' => now()->addDay(),
        ]);
        $assignment->shift->update(['shift_date' => today()]);
        $event->shifts()->create(['shift_date' => today()->addDay(), 'period' => 'afternoon']);
        $paths = [
            '/api/v1/dashboard' => 'data.next_assignment.event_code',
            '/api/v1/assignments' => 'data.0.event_code',
            '/api/v1/assignments/'.$assignment->uuid => 'data.event_code',
            '/api/v1/availability' => 'data.0.event_code',
            '/api/v1/timesheets' => 'data.0.event_code',
        ];
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, 'EDC926');
        }
        $competition->update(['code' => null]);
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, null);
        }
        $competition->update(['code' => 'EDC']);
        $studio = Studio::factory()->create(['code' => 'K2D']);
        $event->update(['event_type' => 'concert', 'studio_id' => $studio->id]);
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, 'K2D2026');
        }
        $dr = EventTypeDefinition::query()->create(['code' => 'DR', 'name' => 'Portrait Shoot', 'system_category' => 'concert', 'is_active' => true]);
        $event->update(['event_type_definition_id' => $dr->id, 'event_date' => '2026-12-10']);
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, 'K2D2026');
        }
        $event->update(['event_date' => '2027-01-01']);
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, 'K2D2027');
        }
        $studio->update(['code' => null]);
        foreach ($paths as $url => $path) {
            $this->getJson($url)->assertOk()->assertJsonPath($path, null);
        }
    }

    public function test_dr_assignment_and_dashboard_use_configured_shift_code(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile);
        $type = EventTypeDefinition::query()->create([
            'code' => 'DR', 'name' => 'Portrait Shoot', 'system_category' => 'concert', 'is_active' => true,
        ]);
        $assignment->shift->schedulingEvent->update([
            'event_type' => 'concert', 'event_type_definition_id' => $type->id,
        ]);
        $assignment->shift->update(['period' => null]);

        $this->getJson('/api/v1/assignments')->assertOk()->assertJsonPath('data.0.shift_code', 'DR');
        $this->getJson('/api/v1/assignments/'.$assignment->uuid)->assertOk()->assertJsonPath('data.shift_code', 'DR');
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.next_assignment.shift_code', 'DR');
        $this->actingAs($profile->user, 'web')->get(route('crew.availability.index'))
            ->assertOk()->assertSee('<div class="type-label">DR</div>', false);
    }

    public function test_crew_lists_and_updates_open_shift_availability(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $event = SchedulingEvent::query()->create([
            'name' => 'Open Competition', 'event_type' => 'competition',
            'event_date' => today()->addMonth(), 'availability_status' => 'open',
            'availability_deadline' => now()->addWeek(),
        ]);
        $venue = Venue::query()->create(['name' => 'Availability Theatre']);
        $competition = CompetitionContact::query()->create([
            'name' => 'Availability Dance Competition',
            'organiser_name' => 'Availability Organiser',
            'organiser_email' => 'availability@example.test',
            'organiser_phone' => '0400 123 456',
            'is_active' => true,
        ]);
        $event->update(['venue_id' => $venue->id, 'competition_contact_id' => $competition->id]);
        $shift = $event->shifts()->create([
            'period' => 'morning',
            'shift_date' => $event->event_date,
            'posted_arrival_at' => $event->event_date->copy()->setTime(8, 0),
            'starts_at' => $event->event_date->copy()->setTime(9, 0),
            'estimated_finish_at' => $event->event_date->copy()->setTime(13, 30),
        ]);

        $this->getJson('/api/v1/availability')->assertOk()
            ->assertJsonPath('data.0.shift_id', $shift->uuid)
            ->assertJsonPath('data.0.status', null)
            ->assertJsonPath('data.0.event_type', 'competition')
            ->assertJsonPath('data.0.organisation.id', $competition->uuid)
            ->assertJsonPath('data.0.organisation.name', 'Availability Dance Competition')
            ->assertJsonPath('data.0.venue.name', 'Availability Theatre')
            ->assertJsonPath('data.0.shift_period', 'morning')
            ->assertJsonPath('data.0.arrival_at', $shift->posted_arrival_at->toIso8601String())
            ->assertJsonPath('data.0.starts_at', $shift->starts_at->toIso8601String())
            ->assertJsonPath('data.0.finish_at', $shift->estimated_finish_at->toIso8601String())
            ->assertJsonPath('data.0.allowed_actions', ['update']);

        $this->putJson('/api/v1/availability/'.$shift->uuid, ['status' => 'available'])
            ->assertUnprocessable()->assertJsonPath('errors.idempotency_key.0', 'A valid Idempotency-Key UUID header is required.');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/availability/'.$shift->uuid, ['status' => 'available', 'note' => 'Available early.'])
            ->assertOk()
            ->assertJsonPath('data.shift_id', $shift->uuid)
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.allowed_actions', ['update', 'clear']);
        $this->assertDatabaseHas('crew_availability_responses', [
            'crew_profile_id' => $profile->id, 'scheduling_shift_id' => $shift->id,
            'status' => 'available', 'note' => 'Available early.',
        ]);

        $this->withoutHeader('Idempotency-Key')
            ->deleteJson('/api/v1/availability/'.$shift->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('errors.idempotency_key.0', 'A valid Idempotency-Key UUID header is required.');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->deleteJson('/api/v1/availability/'.$shift->uuid)
            ->assertOk()
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.allowed_actions', ['update']);
        $this->assertDatabaseMissing('crew_availability_responses', [
            'crew_profile_id' => $profile->id,
            'scheduling_shift_id' => $shift->id,
        ]);
    }

    public function test_crew_can_acknowledge_only_their_own_published_assignment(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $own = $this->assignmentFor($profile);
        $other = $this->assignmentFor($this->completeCrew());

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/assignments/'.$own->uuid.'/acknowledgement')
            ->assertOk()
            ->assertJsonPath('data.id', $own->uuid)
            ->assertJsonPath('data.acknowledged', true);
        $this->assertSame('acknowledged', $own->refresh()->acknowledgement_status);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/assignments/'.$other->uuid.'/acknowledgement')
            ->assertNotFound();
    }

    public function test_assignment_detail_returns_applicable_checklist_and_crew_can_update_it(): void
    {
        [$user, $profile] = $this->authenticatedCrew();
        $assignment = $this->assignmentFor($profile);
        $template = ChecklistTemplate::query()->create([
            'name' => 'Video checklist', 'event_type' => 'competition',
            'role_code' => 'competition-videographer', 'is_active' => true,
        ]);
        $item = $template->items()->create(['instruction' => 'Check camera batteries', 'sort_order' => 1]);

        $this->getJson('/api/v1/assignments/'.$assignment->uuid)
            ->assertOk()
            ->assertJsonPath('data.checklist.0.id', $item->uuid)
            ->assertJsonPath('data.checklist.0.completed', false);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/assignments/'.$assignment->uuid.'/checklist-items/'.$item->uuid, ['completed' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $assignment->uuid)
            ->assertJsonPath('data.checklist.0.completed', true);
        $this->assertDatabaseHas('assignment_checklist_completions', [
            'scheduling_shift_assignment_id' => $assignment->id,
            'checklist_template_item_id' => $item->id,
            'completed_by_user_id' => $user->id,
        ]);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(): array
    {
        $user = User::factory()->crew()->create();
        $profile = $this->completeCrew($user);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    private function completeCrew(?User $user = null): CrewProfile
    {
        $user ??= User::factory()->crew()->create();

        return CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear()->toDateString(),
        ]);
    }

    private function assignmentFor(CrewProfile $profile)
    {
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], [
            'name' => 'Videographer', 'event_type' => 'competition', 'is_active' => true,
        ]);
        $event = SchedulingEvent::query()->create([
            'name' => 'Assigned Competition '.Str::random(5), 'event_type' => 'competition',
            'event_date' => today()->addMonth(),
        ]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);

        return $shift->assignments()->create([
            'crew_profile_id' => $profile->id, 'crew_role_id' => $role->id,
            'status' => 'published', 'published_at' => now(),
        ]);
    }
}
