<?php

namespace Tests\Feature\Scheduling;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileCoverAndTeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_create_list_and_accept_a_cover_request_using_public_ids(): void
    {
        [$ownerUser, $owner] = $this->crew('Owner');
        [$replacementUser, $replacement] = $this->crew('Replacement');
        $assignment = $this->assignment($owner, false, today()->addWeek());
        $replacement->roles()->attach($assignment->crew_role_id, ['status' => 'approved']);
        Sanctum::actingAs($ownerUser, [TokenAbility::CrewMobile->value]);

        $created = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/cover-requests', [
                'recipients' => [$replacement->uuid],
                'message' => 'Can you cover this shift?',
            ])->assertOk()
            ->assertJsonPath('data.direction', 'sent')
            ->assertJsonPath('data.note', 'Can you cover this shift?')
            ->assertJsonPath('data.recipients.0.id', $replacement->uuid);

        $coverId = $created->json('data.id');
        $this->getJson('/api/v1/cover-requests')->assertOk()
            ->assertJsonPath('data.sent.0.id', $coverId)
            ->assertJsonPath('data.received', []);

        Sanctum::actingAs($replacementUser, [TokenAbility::CrewMobile->value]);
        $this->getJson('/api/v1/cover-requests')->assertOk()
            ->assertJsonPath('data.received.0.id', $coverId)
            ->assertJsonPath('data.received.0.requester.id', $owner->uuid);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/cover-requests/'.$coverId.'/accept')->assertOk()
            ->assertJsonPath('data.assignment_id', $assignment->uuid)
            ->assertJsonPath('data.cover_request.request_status', 'accepted');

        $this->assertSame($replacement->id, $assignment->refresh()->crew_profile_id);
    }

    public function test_unrelated_crew_cannot_view_or_accept_a_cover_request(): void
    {
        [$ownerUser, $owner] = $this->crew('Owner');
        [, $recipient] = $this->crew('Recipient');
        [$unrelatedUser] = $this->crew('Unrelated');
        $assignment = $this->assignment($owner, false, today()->addWeek());
        $recipient->roles()->attach($assignment->crew_role_id, ['status' => 'approved']);
        Sanctum::actingAs($ownerUser, [TokenAbility::CrewMobile->value]);
        $coverId = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$assignment->uuid.'/cover-requests', ['recipients' => [$recipient->uuid]])
            ->json('data.id');

        Sanctum::actingAs($unrelatedUser, [TokenAbility::CrewMobile->value]);
        $this->getJson('/api/v1/cover-requests')->assertOk()
            ->assertJsonPath('data.received', [])->assertJsonPath('data.sent', []);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/cover-requests/'.$coverId.'/accept')->assertNotFound();
    }

    public function test_assignment_detail_returns_time_state_and_team_leader_can_finish_the_team(): void
    {
        Carbon::setTestNow(today()->setTime(17, 30));
        [$leaderUser, $leader] = $this->crew('Leader');
        [, $crew] = $this->crew('Crew');
        $leaderAssignment = $this->assignment($leader, true, today());
        $crewRole = CrewRole::query()->create([
            'code' => 'competition-photographer',
            'name' => 'Photographer',
            'event_type' => 'competition',
            'is_active' => true,
        ]);
        $crewAssignment = $leaderAssignment->shift->assignments()->create([
            'crew_profile_id' => $crew->id,
            'crew_role_id' => $crewRole->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        Sanctum::actingAs($leaderUser, [TokenAbility::CrewMobile->value]);

        $this->getJson('/api/v1/assignments/'.$leaderAssignment->uuid)->assertOk()
            ->assertJsonPath('data.time.actual_clock_in_at', null)
            ->assertJsonPath('data.time.locked', false)
            ->assertJsonPath('data.permissions.can_clock_in', true)
            ->assertJsonPath('data.permissions.can_finish_team', true);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/assignments/'.$leaderAssignment->uuid.'/finish-team', [
                'actual_finish_at' => '17:30',
                'optional_note' => 'Team complete.',
            ])->assertOk()->assertJsonPath('data.updated_count', 2);

        $this->assertSame('17:30', $leaderAssignment->timeEntry()->firstOrFail()->actual_finish_at->format('H:i'));
        $this->assertSame('17:30', $crewAssignment->timeEntry()->firstOrFail()->actual_finish_at->format('H:i'));
    }

    /** @return array{User, CrewProfile} */
    private function crew(string $name): array
    {
        $user = User::factory()->crew()->create(['name' => $name]);
        $profile = CrewProfile::factory()->for($user)->create([
            'preferred_name' => $name,
            'phone' => '0400 000 000',
            'address_line_1' => '1 Test Street',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear(),
        ]);

        return [$user, $profile];
    }

    private function assignment(CrewProfile $profile, bool $teamLeader, mixed $date)
    {
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], [
            'name' => 'Videographer', 'event_type' => 'competition', 'is_active' => true,
        ]);
        $profile->roles()->syncWithoutDetaching([$role->id => ['status' => 'approved']]);
        $event = SchedulingEvent::query()->create([
            'name' => 'Mobile Competition '.Str::random(4),
            'event_type' => 'competition',
            'event_date' => $date,
        ]);
        $shift = $event->shifts()->create([
            'shift_date' => $date,
            'posted_arrival_at' => $date->copy()->setTime(8, 0),
            'starts_at' => $date->copy()->setTime(8, 30),
            'estimated_finish_at' => $date->copy()->setTime(17, 0),
        ]);

        return $shift->assignments()->create([
            'crew_profile_id' => $profile->id,
            'crew_role_id' => $role->id,
            'is_team_leader' => $teamLeader,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
