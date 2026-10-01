<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\CrewAssignmentOptions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewAssignmentOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_trained_crew_sort_first_and_all_other_active_crew_remain_selectable(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $role = CrewRole::factory()->create(['event_type' => 'competition']);
        $event = SchedulingEvent::create(['name' => 'Roster options test', 'event_type' => 'competition', 'event_date' => today()->addMonth(), 'availability_status' => 'open', 'availability_deadline' => now()->addWeek()]);
        $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $shift = $event->shifts()->create(['shift_date' => $event->event_date, 'starts_at' => $event->event_date->copy()->setTime(18, 0), 'estimated_finish_at' => $event->event_date->copy()->setTime(20, 0)]);
        $profiles = collect();
        foreach ([['Zara', 'approved', 'available'], ['Ben', 'approved', 'available'], ['Amy', 'approved', 'unavailable'], ['Cory', null, 'available'], ['Dani', 'approved', null]] as [$name, $training, $availability]) {
            $profile = CrewProfile::factory()->for(User::factory()->crew())->create(['preferred_name' => $name]);
            if ($training) {
                $profile->roles()->attach($role, ['status' => $training]);
            }
            if ($availability) {
                $shift->availabilityResponses()->create(['crew_profile_id' => $profile->id, 'status' => $availability, 'responded_at' => now()]);
            }
            $profiles->push($profile);
        }
        $conflict = SchedulingEvent::create(['name' => 'Overlap', 'event_type' => 'competition', 'event_date' => $event->event_date]);
        $conflictingShift = $conflict->shifts()->create(['shift_date' => $event->event_date, 'starts_at' => $shift->starts_at, 'estimated_finish_at' => $shift->estimated_finish_at]);
        $conflictingShift->assignments()->create(['crew_profile_id' => $profiles[1]->id, 'crew_role_id' => $role->id, 'status' => 'draft']);
        $options = app(CrewAssignmentOptions::class)->forRole($profiles->each->load('roles', 'user'), $role, $shift->availabilityResponses()->get()->keyBy('crew_profile_id'));
        $this->assertSame(['Ben', 'Zara'], $options['recommended']->pluck('name')->all());
        $this->assertSame(['Amy', 'Cory', 'Dani'], $options['other']->pluck('name')->all());
        $this->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('✓ Ben')->assertSee('✓ Zara')->assertSee('Other crew');
        $url = route('admin.scheduling-shifts.roles.assignment', [$shift, $role]);
        $this->putJson($url, ['crew_profile_uuid' => $profiles[3]->uuid])->assertOk();
        $this->assertSame($profiles[3]->id, $shift->assignments()->sole()->crew_profile_id);
        $this->assertFalse($profiles[3]->roles()->whereKey($role->id)->exists());
        $profiles[3]->user->update(['is_active' => false]);
        $this->putJson($url, ['crew_profile_uuid' => $profiles[3]->uuid])->assertUnprocessable();
    }
}
