<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimekeepingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_early_clock_in_is_recorded_but_pay_starts_at_posted_arrival(): void
    {
        Carbon::setTestNow('2026-09-10 07:45:00');
        [$crewUser, $assignment] = $this->assignment('2026-09-10 08:00:00');
        $this->actingAs($crewUser)->post(route('crew.assignments.time.clock-in', $assignment))->assertRedirect();

        $entry = $assignment->timeEntry()->firstOrFail();
        $this->assertSame('07:45', $entry->actual_clock_in_at->format('H:i'));
        $this->assertSame('08:00', $entry->payable_start_at->format('H:i'));
    }

    public function test_late_clock_in_becomes_the_payable_start(): void
    {
        Carbon::setTestNow('2026-09-10 08:07:00');
        [$crewUser, $assignment] = $this->assignment('2026-09-10 08:00:00');
        $this->actingAs($crewUser)->post(route('crew.assignments.time.clock-in', $assignment));

        $entry = $assignment->timeEntry()->firstOrFail();
        $this->assertSame('08:07', $entry->payable_start_at->format('H:i'));
        $this->assertContains('Clocked in after posted arrival', $entry->reviewFlags());
    }

    public function test_crew_can_finish_a_clocked_in_shift_now(): void
    {
        Carbon::setTestNow('2026-09-10 08:00:00');
        [$crewUser, $assignment] = $this->assignment('2026-09-10 08:00:00');
        $this->actingAs($crewUser)->post(route('crew.assignments.time.clock-in', $assignment))->assertRedirect();

        Carbon::setTestNow('2026-09-10 17:12:00');
        $this->actingAs($crewUser)->post(route('crew.assignments.time.finish', $assignment))->assertRedirect();

        $entry = $assignment->timeEntry()->firstOrFail();
        $this->assertSame('08:00', $entry->actual_clock_in_at->format('H:i'));
        $this->assertSame('17:12', $entry->actual_finish_at->format('H:i'));
    }

    public function test_next_shift_switches_from_clock_in_to_clock_out_two_hours_after_arrival(): void
    {
        Carbon::setTestNow('2026-09-10 07:30:00');
        [$crewUser, $assignment] = $this->assignment('2026-09-10 08:00:00');

        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertSee('Next shift')
            ->assertSee('>Clock in</button>', false)
            ->assertSee('Pre-Start Checks')
            ->assertSee('Shift starts soon')
            ->assertSee('data-shift-start', false)
            ->assertDontSee('>Clock out</button>', false);

        $this->actingAs($crewUser)->post(route('crew.assignments.time.clock-in', $assignment))->assertRedirect();
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertSee('>Clock out</button>', false)
            ->assertDontSee('>Clock in</button>', false);

        $assignment->timeEntry()->delete();

        Carbon::setTestNow('2026-09-10 10:01:00');
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertSee('>Clock out</button>', false)
            ->assertDontSee('>Clock in</button>', false);

        $this->actingAs($crewUser)->post(route('crew.assignments.time.finish', $assignment))->assertRedirect();
        $entry = $assignment->timeEntry()->firstOrFail();
        $this->assertNull($entry->actual_clock_in_at);
        $this->assertSame('10:01', $entry->actual_finish_at->format('H:i'));
    }

    public function test_crew_can_correct_times_without_providing_a_reason(): void
    {
        [$crewUser, $assignment] = $this->assignment('2026-09-10 08:00:00');
        $this->actingAs($crewUser)->put(route('crew.assignments.time.update', $assignment), ['actual_clock_in_at' => '2026-09-10 08:05:00', 'actual_finish_at' => '2026-09-10 17:00:00'])->assertRedirect();
        $this->actingAs($crewUser)->put(route('crew.assignments.time.update', $assignment), ['actual_clock_in_at' => '2026-09-10 08:00:00', 'actual_finish_at' => '2026-09-10 17:15:00'])->assertRedirect();

        $entry = $assignment->timeEntry()->firstOrFail();
        $this->assertSame('17:15', $entry->actual_finish_at->format('H:i'));
        $this->assertCount(2, $entry->audits);
        $this->assertTrue($entry->audits->every(fn ($audit): bool => $audit->optional_note === null));
    }

    public function test_team_leader_finishes_all_crew_without_overwriting_existing_finish(): void
    {
        Carbon::setTestNow('2026-09-10 17:42:00');
        [$leaderUser, $leaderAssignment, $event] = $this->assignment('2026-09-10 08:00:00', true);
        [, $crewAssignment] = $this->assignment('2026-09-10 08:00:00', false, $event);
        [, $alreadyFinished] = $this->assignment('2026-09-10 08:00:00', false, $event);
        $alreadyFinished->timeEntry()->create(['actual_finish_at' => '2026-09-10 16:30:00', 'finish_recorded_at' => now(), 'finish_source' => 'crew']);

        $this->actingAs($leaderUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()->assertSee('Clock crew out')->assertSee('Clocked out 4:30 pm');

        $this->actingAs($leaderUser)->post(route('crew.assignments.time.finish-team', $leaderAssignment), [
            'actual_finish_at' => '17:42',
        ])->assertRedirect();

        $this->assertSame('17:42', $crewAssignment->timeEntry()->firstOrFail()->actual_finish_at->format('H:i'));
        $this->assertSame('17:42', $leaderAssignment->timeEntry()->firstOrFail()->actual_finish_at->format('H:i'));
        $this->assertSame('16:30', $alreadyFinished->timeEntry()->firstOrFail()->actual_finish_at->format('H:i'));
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $crewAssignment->crewProfile->user_id, 'type' => 'shift_finished_by_team_leader']);

        $this->actingAs($leaderUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertSee('Crew clocked out')
            ->assertDontSee('Clock all crew out');

        $this->actingAs($leaderUser)->post(route('crew.assignments.time.finish-team', $leaderAssignment), [
            'actual_finish_at' => '17:50',
        ])->assertStatus(422);
    }

    public function test_crew_cannot_update_another_persons_time(): void
    {
        [, $assignment] = $this->assignment('2026-09-10 08:00:00');
        $otherUser = User::factory()->crew()->create();
        CrewProfile::factory()->for($otherUser)->create();
        $this->actingAs($otherUser)->put(route('crew.assignments.time.update', $assignment), ['actual_clock_in_at' => '2026-09-10 08:00:00'])->assertForbidden();
    }

    public function test_admin_can_review_and_correct_time_without_a_required_note(): void
    {
        $staff = User::factory()->staff()->create();
        [$crewUser, $assignment, $event] = $this->assignment('2026-09-10 08:00:00');
        $this->actingAs($crewUser)->put(route('crew.assignments.time.update', $assignment), ['actual_clock_in_at' => '2026-09-10 08:05:00'])->assertRedirect();

        $this->actingAs($staff)->get(route('admin.scheduling-events.show', $event))
            ->assertOk()->assertSee('Crew time records')->assertSee('Check recommended: Clocked in after posted arrival');
        $this->actingAs($staff)->put(route('admin.scheduling-assignments.time.update', $assignment), [
            'actual_clock_in_at' => '2026-09-10 08:00:00', 'actual_finish_at' => '2026-09-10 17:00:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('assignment_time_entry_audits', ['changed_by_user_id' => $staff->id, 'field' => 'actual_clock_in_at', 'optional_note' => null]);
    }

    private function assignment(string $postedArrival, bool $teamLeader = false, ?SchedulingEvent $event = null): array
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $role = CrewRole::factory()->create();
        $event ??= SchedulingEvent::query()->create(['name' => 'Competition', 'event_type' => 'competition', 'event_date' => '2026-09-10']);
        $shift = $event->shifts()->firstOrCreate(['shift_date' => '2026-09-10', 'period' => 'morning'], ['posted_arrival_at' => $postedArrival, 'starts_at' => '2026-09-10 08:30:00', 'estimated_finish_at' => '2026-09-10 17:00:00']);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published', 'is_team_leader' => $teamLeader]);

        return [$user, $assignment, $event];
    }
}
