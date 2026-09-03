<?php

namespace Tests\Feature\Scheduling;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileSchedulingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_lists_and_updates_open_shift_availability(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $event = SchedulingEvent::query()->create([
            'name' => 'Open Competition', 'event_type' => 'competition',
            'event_date' => today()->addMonth(), 'availability_status' => 'open',
            'availability_deadline' => now()->addWeek(),
        ]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);

        $this->getJson('/api/v1/availability')->assertOk()
            ->assertJsonPath('data.0.shift_id', $shift->uuid)
            ->assertJsonPath('data.0.status', null);

        $this->putJson('/api/v1/availability/'.$shift->uuid, ['status' => 'available'])
            ->assertUnprocessable()->assertJsonPath('errors.idempotency_key.0', 'A valid Idempotency-Key UUID header is required.');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/availability/'.$shift->uuid, ['status' => 'available', 'note' => 'Available early.'])
            ->assertOk();
        $this->assertDatabaseHas('crew_availability_responses', [
            'crew_profile_id' => $profile->id, 'scheduling_shift_id' => $shift->id,
            'status' => 'available', 'note' => 'Available early.',
        ]);
    }

    public function test_crew_can_acknowledge_only_their_own_published_assignment(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $own = $this->assignmentFor($profile);
        $other = $this->assignmentFor($this->completeCrew());

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/assignments/'.$own->uuid.'/acknowledgement')
            ->assertOk();
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
            ->assertOk();
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
