<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EquipmentScheduleDetails;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentScheduleDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flags_an_unexplained_change_between_taker_and_next_bringer(): void
    {
        [$firstAssignment] = $this->assignment('First Concert', 5, 'Alex');
        [$secondAssignment] = $this->assignment('Second Concert', 8, 'Jess');
        $first = $firstAssignment->equipmentResponsibilities()->create(['item_code' => 'video_1', 'is_taking' => true]);
        $second = $secondAssignment->equipmentResponsibilities()->create(['item_code' => 'video_1', 'is_bringing' => true]);

        $details = app(EquipmentScheduleDetails::class)->execute();

        $this->assertSame('Second Concert', str($details[$first->id]['next'])->before(' · ')->toString());
        $this->assertTrue($details[$second->id]['warnings']->contains(fn (string $warning): bool => str_contains($warning, 'do not match')));
    }

    public function test_other_notes_explain_a_transfer_without_a_red_flag(): void
    {
        [$firstAssignment] = $this->assignment('First Concert', 5, 'Alex');
        [$secondAssignment] = $this->assignment('Second Concert', 8, 'Jess');
        $firstAssignment->equipmentResponsibilities()->create(['item_code' => 'video_1', 'is_taking' => true, 'other_notes' => 'Drop off to Jess on Monday.']);
        $second = $secondAssignment->equipmentResponsibilities()->create(['item_code' => 'video_1', 'is_bringing' => true]);

        $details = app(EquipmentScheduleDetails::class)->execute();

        $this->assertTrue($details[$second->id]['warnings']->isEmpty());
        $this->assertTrue($details[$second->id]['continuity']->contains(fn (string $note): bool => str_contains($note, 'explained in Other')));
    }

    public function test_it_recognises_equipment_staying_at_the_same_venue(): void
    {
        $venue = Venue::query()->create(['name' => 'Weekend Venue']);
        [$firstAssignment] = $this->assignment('Saturday Concert', 5, 'Alex', $venue);
        [$secondAssignment] = $this->assignment('Sunday Concert', 6, 'Alex', $venue);
        $first = $firstAssignment->equipmentResponsibilities()->create(['item_code' => 'backdrop_1', 'other_notes' => 'Leave at venue overnight.']);
        $second = $secondAssignment->equipmentResponsibilities()->create(['item_code' => 'backdrop_1', 'other_notes' => 'Already at venue.']);

        $details = app(EquipmentScheduleDetails::class)->execute();

        $this->assertTrue($details[$first->id]['continuity']->contains(fn (string $note): bool => str_contains($note, 'Stays at this venue')));
        $this->assertTrue($details[$second->id]['warnings']->isEmpty());
    }

    private function assignment(string $eventName, int $daysFromNow, string $crewName, ?Venue $venue = null): array
    {
        $user = User::factory()->crew()->create(['name' => $crewName]);
        $crew = CrewProfile::factory()->for($user)->create(['preferred_name' => $crewName]);
        $role = CrewRole::factory()->create();
        $venue ??= Venue::query()->create(['name' => $eventName.' Venue']);
        $date = now()->addDays($daysFromNow);
        $event = SchedulingEvent::query()->create(['name' => $eventName, 'event_type' => 'concert', 'event_date' => $date, 'venue_id' => $venue->id]);
        $shift = $event->shifts()->create(['shift_date' => $date, 'starts_at' => $date->copy()->setTime(18, 0), 'estimated_finish_at' => $date->copy()->setTime(21, 0)]);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $crew->id, 'crew_role_id' => $role->id, 'status' => 'draft']);

        return [$assignment, $event];
    }
}
