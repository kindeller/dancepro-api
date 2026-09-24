<?php

namespace Tests\Feature\Programmes;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class ProgrammePhotographersTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private function event(string $type = 'competition'): SchedulingEvent
    {
        return SchedulingEvent::create(['name' => 'Roster test', 'event_type' => $type, 'event_date' => '2026-09-04']);
    }

    private function url(SchedulingEvent $event): string
    {
        return "/api/staff/events/{$event->uuid}/programme/photographers";
    }

    private function signIn(array $abilities = ['programmes:read']): void
    {
        $this->actingAsApiUser(User::factory()->staff()->create(), $abilities);
    }

    public function test_published_photographers_are_grouped_across_shifts_without_private_profile_fields(): void
    {
        $this->signIn();
        $event = $this->event();
        $role = CrewRole::where('code', 'competition-photographer-p1')->firstOrFail();
        $crew = CrewProfile::factory()->create(['preferred_name' => 'Alex']);
        foreach (['morning', 'afternoon'] as $period) {
            $shift = $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => $period]);
            $shift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $crew->id, 'status' => 'published']);
        }
        $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $response = $this->getJson($this->url($event))->assertOk()
            ->assertJsonCount(1, 'data.photographers')->assertJsonCount(2, 'data.photographers.0.shifts')
            ->assertJsonPath('data.photographers.0.name', 'Alex')->assertJsonPath('data.unfilled', []);
        $person = $response->json('data.photographers.0');
        $this->assertEqualsCanonicalizing(['id', 'crew_uuid', 'role_uuid', 'name', 'role_name', 'shifts'], array_keys($person));
        $this->assertSame($crew->uuid, $person['crew_uuid']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_draft_inactive_unassigned_and_nonphoto_roles_are_not_selectable(): void
    {
        $this->signIn();
        $event = $this->event();
        $shift = $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'morning']);
        foreach (['competition-photographer-p1', 'competition-photographer-p2', 'competition-videographer'] as $index => $code) {
            $role = CrewRole::where('code', $code)->firstOrFail();
            $crew = CrewProfile::factory()->create();
            if ($index === 1) {
                $crew->user->update(['is_active' => false]);
            }
            $shift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $crew->id, 'status' => $index === 0 ? 'draft' : 'published']);
            $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        }
        $this->getJson($this->url($event))->assertOk()->assertJsonPath('data.photographers', [])
            ->assertJsonCount(2, 'data.unfilled');
    }

    public function test_shift_requirement_overrides_default_and_other_event_assignments_do_not_leak(): void
    {
        $this->signIn();
        $event = $this->event();
        $other = $this->event();
        $role = CrewRole::where('code', 'competition-photographer-p1')->firstOrFail();
        $crew = CrewProfile::factory()->create();
        $shift = $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'morning']);
        $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $shift->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 2]);
        $otherShift = $other->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'morning']);
        $otherShift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $crew->id, 'status' => 'published']);
        $this->getJson($this->url($event))->assertOk()->assertJsonPath('data.photographers', [])
            ->assertJsonPath('data.unfilled.0.missing_count', 2);
    }

    public function test_concert_period_can_be_null_and_no_shifts_is_explicit(): void
    {
        $this->signIn();
        $event = $this->event('concert');
        $this->getJson($this->url($event))->assertOk()->assertJsonPath('data.has_shifts', false);
        $role = CrewRole::where('code', 'photographer-p')->firstOrFail();
        $crew = CrewProfile::factory()->create();
        $shift = $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => null]);
        $shift->assignments()->create(['crew_role_id' => $role->id, 'crew_profile_id' => $crew->id, 'status' => 'published']);
        $this->getJson($this->url($event))->assertOk()->assertJsonPath('data.photographers.0.shifts.0.period', 'concert');
    }

    public function test_requires_admin_and_read_ability_and_rejects_cancelled_event(): void
    {
        $event = $this->event();
        $this->getJson($this->url($event))->assertUnauthorized();
        $this->signIn(['account:read']);
        $this->getJson($this->url($event))->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($this->url($event))->assertForbidden();
        $this->signIn();
        $event->update(['cancelled_at' => now()]);
        $this->getJson($this->url($event))->assertConflict();
    }
}
