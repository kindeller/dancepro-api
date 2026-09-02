<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCoverRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_send_a_personalised_request_to_multiple_eligible_people(): void
    {
        [$owner, $assignment, $role] = $this->assignment();
        [$first] = $this->qualifiedCrew($role, 'Avery');
        [$second] = $this->qualifiedCrew($role, 'Bailey');

        $this->actingAs($owner)->get(route('crew.cover.create', $assignment))->assertOk()
            ->assertSee('Select all eligible crew')->assertSee('Optional personalised message');
        $this->actingAs($owner)->post(route('crew.cover.store', $assignment), [
            'recipients' => [$first->uuid, $second->uuid],
            'message' => 'Would either of you be able to help me with this one?',
        ])->assertRedirect(route('crew.availability.index', ['view' => 'cover']));

        $this->assertDatabaseHas('shift_cover_requests', ['scheduling_shift_assignment_id' => $assignment->id, 'message' => 'Would either of you be able to help me with this one?', 'status' => 'open']);
        $this->assertDatabaseCount('shift_cover_request_recipients', 2);
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $first->user_id, 'type' => 'cover_request']);
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $second->user_id, 'type' => 'cover_request']);
        $this->actingAs($owner)->get(route('crew.cover.index'))->assertRedirect(route('crew.availability.index', ['view' => 'cover']));
        $this->actingAs($owner)->get(route('crew.availability.index', ['view' => 'cover']))->assertOk()->assertSee('Cover you have organised');
        $this->actingAs($owner)->get(route('crew.availability.index', ['view' => 'upcoming']))->assertOk()->assertSee('Cover Requested');
        $this->actingAs($first->user)->get(route('crew.availability.index'))->assertOk()->assertSee('Requests sent to you');
        $this->actingAs($first->user)->get(route('crew.availability.index', ['view' => 'cover']))->assertOk()->assertSee('Requests sent to you');
    }

    public function test_first_acceptance_transfers_shift_and_closes_other_requests(): void
    {
        User::factory()->staff()->create();
        [$owner, $assignment, $role] = $this->assignment();
        [$first, $firstUser] = $this->qualifiedCrew($role, 'Avery');
        [$second, $secondUser] = $this->qualifiedCrew($role, 'Bailey');
        $assignment->equipmentResponsibilities()->create(['item_code' => 'video_1', 'is_bringing' => true, 'is_taking' => false]);
        $this->actingAs($owner)->post(route('crew.cover.store', $assignment), ['recipients' => [$first->uuid, $second->uuid]]);
        $cover = $assignment->coverRequests()->firstOrFail();

        $this->actingAs($firstUser)->post(route('crew.cover.accept', $cover))->assertRedirect(route('crew.assignments.show', $assignment));

        $assignment->refresh();
        $this->assertSame($first->id, $assignment->crew_profile_id);
        $this->assertSame('not_acknowledged', $assignment->acknowledgement_status);
        $this->assertTrue($assignment->equipmentResponsibilities()->where('item_code', 'video_1')->exists());
        $this->assertDatabaseHas('shift_cover_requests', ['id' => $cover->id, 'status' => 'accepted', 'accepted_by_crew_profile_id' => $first->id]);
        $this->assertDatabaseHas('shift_cover_request_recipients', ['shift_cover_request_id' => $cover->id, 'crew_profile_id' => $second->id, 'status' => 'closed']);
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $owner->id, 'title' => 'Cover confirmed']);
        $this->actingAs($secondUser)->post(route('crew.cover.accept', $cover))->assertSessionHasErrors('cover');
        $this->assertSame($first->id, $assignment->fresh()->crew_profile_id);
    }

    public function test_unqualified_or_conflicting_crew_cannot_be_sent_or_accept_cover(): void
    {
        [$owner, $assignment, $role] = $this->assignment();
        [$eligible, $eligibleUser] = $this->qualifiedCrew($role, 'Eligible');
        [$unqualified] = $this->crew('Unqualified');
        [$conflicting] = $this->qualifiedCrew($role, 'Busy');
        $conflictingAssignment = $this->makeAssignment($conflicting, $role, '2026-09-10');

        $this->actingAs($owner)->post(route('crew.cover.store', $assignment), ['recipients' => [$unqualified->uuid]])->assertSessionHasErrors('recipients');
        $this->actingAs($owner)->post(route('crew.cover.store', $assignment), ['recipients' => [$conflicting->uuid]])->assertSessionHasErrors('recipients');
        $this->actingAs($owner)->post(route('crew.cover.store', $assignment), ['recipients' => [$eligible->uuid]])->assertRedirect();
        $cover = $assignment->coverRequests()->firstOrFail();
        $this->actingAs($eligibleUser)->post(route('crew.cover.accept', $cover))->assertRedirect();
        $this->assertNotSame($conflictingAssignment->crew_profile_id, $assignment->fresh()->crew_profile_id);
    }

    private function assignment(): array
    {
        [$profile, $user] = $this->crew('Original');
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], ['name' => 'Competition Videographer V', 'event_type' => 'competition', 'is_active' => true]);
        $profile->roles()->syncWithoutDetaching([$role->id => ['status' => 'approved']]);

        return [$user, $this->makeAssignment($profile, $role, '2026-09-10'), $role];
    }

    private function makeAssignment(CrewProfile $profile, CrewRole $role, string $date): SchedulingShiftAssignment
    {
        $event = SchedulingEvent::query()->create(['name' => fake()->unique()->company(), 'event_type' => 'competition', 'event_date' => $date, 'roster_status' => 'published']);
        $shift = $event->shifts()->create(['shift_date' => $date, 'period' => 'morning', 'posted_arrival_at' => "$date 08:00:00", 'starts_at' => "$date 08:30:00", 'estimated_finish_at' => "$date 17:00:00"]);

        return $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published', 'acknowledgement_status' => 'acknowledged']);
    }

    private function qualifiedCrew(CrewRole $role, string $name): array
    {
        [$profile, $user] = $this->crew($name);
        $profile->roles()->attach($role->id, ['status' => 'approved']);

        return [$profile, $user];
    }

    private function crew(string $name): array
    {
        $user = User::factory()->crew()->create(['name' => $name]);
        $profile = CrewProfile::factory()->for($user)->create(['preferred_name' => $name]);

        return [$profile, $user];
    }
}
