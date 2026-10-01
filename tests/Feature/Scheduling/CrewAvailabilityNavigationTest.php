<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewAvailabilityNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_answering_last_request_keeps_availability_tab_instead_of_switching_to_acknowledge(): void
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create();
        $event = SchedulingEvent::create(['name' => 'Open availability', 'event_type' => 'concert', 'event_date' => today()->addMonth(), 'availability_status' => 'open', 'availability_deadline' => now()->addWeek()]);
        $shift = $event->shifts()->create(['shift_date' => $event->event_date]);
        $other = SchedulingEvent::create(['name' => 'Awaiting acknowledgement', 'event_type' => 'concert', 'event_date' => today()->addMonth(), 'availability_status' => 'closed']);
        $otherShift = $other->shifts()->create(['shift_date' => $other->event_date]);
        $otherShift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => CrewRole::factory()->create()->id, 'status' => 'published', 'acknowledgement_status' => 'not_acknowledged']);

        $home = route('crew.availability.index');
        $this->actingAs($user)->get($home)->assertOk()->assertViewHas('filter', 'availability');
        $target = route('crew.availability.index', ['view' => 'availability', 'layout' => 'detailed']);
        $this->from($home)->put(route('crew.availability.store', $shift), ['status' => 'available', 'layout' => 'detailed'])->assertRedirect($target);
        $this->get($target)->assertOk()->assertViewHas('filter', 'availability')->assertViewHas('pendingAvailability', 0)->assertSee('Availability saved');
        $this->from($home)->delete(route('crew.availability.clear', $shift), ['layout' => 'detailed'])->assertRedirect($target);
        $this->get($target)->assertOk()->assertViewHas('filter', 'availability')->assertViewHas('pendingAvailability', 1);

        $grid = route('crew.availability.index', ['view' => 'availability', 'layout' => 'grid']);
        $this->from($grid)->put(route('crew.availability.store', $shift), ['status' => 'unavailable', 'layout' => 'grid'])->assertRedirect($grid);
        $this->from($grid)->delete(route('crew.availability.clear', $shift), ['layout' => 'grid'])->assertRedirect($grid);
        $this->putJson(route('crew.availability.store', $shift), ['status' => 'available', 'layout' => 'grid'])->assertOk()->assertJsonPath('status', 'available');
        $this->get($grid)->assertOk()->assertViewHas('filter', 'availability')->assertViewHas('availabilityLayout', 'grid');
    }
}
