<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EventRoleOptions;
use App\Features\Scheduling\Services\EventRosterStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventWorkflowControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_slots_offer_applicable_roles_and_add_p2_once_without_opening_availability(): void
    {
        [$event, $p2] = $this->fixture();
        $wrong = CrewRole::factory()->create(['name' => 'Competition-only example', 'event_type' => 'competition']);
        $this->get(route('admin.scheduling-events.index'))->assertOk()
            ->assertSee('class="add-event-role"', false)->assertSee($p2->name)->assertDontSee($wrong->name)
            ->assertSee('event-status-dialog');
        $url = route('admin.scheduling-events.roles.store', $event);
        $this->postJson($url, ['role_uuid' => $p2->uuid])->assertOk();
        $this->postJson($url, ['role_uuid' => $p2->uuid])->assertOk();
        $this->assertSame(2, $event->roleRequirements()->count());
        $this->assertSame('draft', $event->fresh()->availability_status->value);
        $this->assertDatabaseCount('crew_notifications', 0);
        $this->assertFalse(app(EventRosterStatus::class)->forEvent($event->fresh())['ready']);
        $this->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('Pencil Concert Photographer P2');
    }

    public function test_roles_reject_inactive_wrong_category_cancelled_and_full_events(): void
    {
        [$event, $p2] = $this->fixture();
        $url = route('admin.scheduling-events.roles.store', $event);
        foreach ([['is_active' => false, 'event_type' => 'concert'], ['event_type' => 'competition']] as $attributes) {
            $role = CrewRole::factory()->create($attributes);
            $this->postJson($url, ['role_uuid' => $role->uuid])->assertUnprocessable()->assertJsonValidationErrors('role_uuid');
        }
        $event->update(['cancelled_at' => now()]);
        $this->postJson($url, ['role_uuid' => $p2->uuid])->assertUnprocessable();
        $event->update(['cancelled_at' => null]);
        foreach (range(1, 2) as $unused) {
            $event->roleRequirements()->create(['crew_role_id' => CrewRole::factory()->create(['event_type' => 'concert'])->id, 'quantity' => 1]);
        }
        $this->postJson($url, ['role_uuid' => $p2->uuid])->assertUnprocessable();
        $this->assertSame(3, $event->roleRequirements()->count());
    }

    public function test_status_actions_require_deadline_preserve_responses_and_enforce_roster_readiness(): void
    {
        [$event] = $this->fixture();
        $url = route('admin.scheduling-events.workflow', $event);
        $this->patchJson($url, ['action' => 'open'])->assertUnprocessable()->assertJsonValidationErrors('deadline');
        $this->patchJson($url, ['action' => 'open', 'deadline' => today()->addWeek()->toDateString()])->assertOk();
        $this->assertSame('open', $event->fresh()->availability_status->value);
        $this->assertSame('17:00', $event->fresh()->availability_deadline->format('H:i'));
        $this->patchJson($url, ['action' => 'closed'])->assertOk();
        $this->assertSame('closed', $event->fresh()->availability_status->value);
        $this->patchJson($url, ['action' => 'draft'])->assertOk();
        $this->assertSame('draft', $event->fresh()->availability_status->value);
        $this->patchJson($url, ['action' => 'publish_roster'])->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->patchJson($url, ['action' => 'ready'])->assertUnprocessable();
        $event->update(['roster_status' => 'published']);
        $this->patchJson($url, ['action' => 'draft'])->assertUnprocessable();
        $event->update(['cancelled_at' => now()]);
        $this->patchJson($url, ['action' => 'closed'])->assertUnprocessable();
    }

    public function test_customers_cannot_change_event_status_or_add_roles(): void
    {
        [$event, $p2] = $this->fixture();
        $this->actingAs(User::factory()->customer()->create());
        $this->postJson(route('admin.scheduling-events.roles.store', $event), ['role_uuid' => $p2->uuid])->assertForbidden();
        $this->patchJson(route('admin.scheduling-events.workflow', $event), ['action' => 'closed'])->assertForbidden();
        $this->assertSame(1, $event->roleRequirements()->count());
    }

    public function test_concert_roles_keep_fixed_columns_and_new_role_reopens_readiness(): void
    {
        [$event, $p2] = $this->fixture();
        $p1 = $event->roleRequirements()->first()->crew_role_id;
        $profile = CrewProfile::factory()->create();
        $event->shifts()->first()->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $p1, 'status' => 'draft', 'acknowledgement_status' => 'not_acknowledged']);
        $this->patchJson(route('admin.scheduling-events.workflow', $event), ['action' => 'publish_roster'])->assertOk();
        $assignment = $event->shifts()->first()->assignments()->first();
        $this->assertSame('published', $assignment->status);
        $this->assertDatabaseCount('crew_notifications', 1);
        $assignment->update(['acknowledgement_status' => 'acknowledged']);
        $this->assertTrue(app(EventRosterStatus::class)->forEvent($event->fresh())['ready']);
        $this->postJson(route('admin.scheduling-events.roles.store', $event), ['role_uuid' => $p2->uuid])->assertOk();
        $this->assertFalse(app(EventRosterStatus::class)->forEvent($event->fresh())['ready']);
        $this->assertSame('changed', $event->fresh()->roster_status);
        $slots = app(EventRoleOptions::class)->slots($event->fresh());
        $this->assertNull($slots[0]);
        $this->assertSame('concert-photographer-p1', $slots[1]->crewRole->code);
        $this->assertSame('concert-photographer-p2', $slots[2]->crewRole->code);
        $video = CrewRole::firstOrCreate(['code' => 'concert-videographer'], ['name' => 'Concert Videographer V', 'event_type' => 'concert', 'is_active' => true]);
        $this->postJson(route('admin.scheduling-events.roles.store', $event), ['role_uuid' => $video->uuid])->assertOk();
        $slots = app(EventRoleOptions::class)->slots($event->fresh());
        $this->assertSame('concert-videographer', $slots[0]->crewRole->code);
        $this->assertDatabaseCount('crew_notifications', 1);
    }

    private function fixture(): array
    {
        $this->actingAs(User::factory()->staff()->create());
        $event = SchedulingEvent::create(['name' => 'Concert role test', 'event_type' => 'concert', 'event_date' => today()->addMonth(), 'availability_status' => 'draft', 'roster_status' => 'draft']);
        $event->shifts()->create(['shift_date' => $event->event_date, 'starts_at' => today()->addMonth()->setTime(18, 0), 'estimated_finish_at' => today()->addMonth()->setTime(20, 0)]);
        $p1 = CrewRole::firstOrCreate(['code' => 'concert-photographer-p1'], ['name' => 'Concert Photographer P1', 'event_type' => 'concert']);
        $p2 = CrewRole::firstOrCreate(['code' => 'concert-photographer-p2'], ['name' => 'Concert Photographer P2', 'event_type' => 'concert']);
        $event->roleRequirements()->create(['crew_role_id' => $p1->id, 'quantity' => 1]);

        return [$event, $p2];
    }
}
