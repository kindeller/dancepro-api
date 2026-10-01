<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Services\EquipmentScheduleDetails;
use App\Features\Scheduling\Support\AssignmentEquipmentItems;
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

    public function test_competition_assignments_support_all_bring_take_options_and_notes(): void
    {
        [$assignment, $event] = $this->assignment('Competition equipment', 10, 'Equipment crew');
        $event->update(['event_type' => 'competition']);
        $event->roleRequirements()->create(['crew_role_id' => $assignment->crew_role_id, 'quantity' => 1]);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.scheduling-events.index'))->assertOk()
            ->assertSee('Bring Video 1')->assertSee('Take Backdrop 2')->assertSee('Changes save automatically.');
        $url = route('admin.scheduling-assignments.equipment', $assignment);
        foreach (['video_1', 'video_2', 'video_3', 'backdrop_1', 'backdrop_2', 'photos', 'videos'] as $code) {
            $this->putJson($url, ['item_code' => $code, 'is_bringing' => true, 'is_taking' => true, 'other_notes' => 'Leave at stage door.'])->assertOk();
        }
        $this->assertSame(7, $assignment->equipmentResponsibilities()->count());
        $this->assertTrue($assignment->equipmentResponsibilities()->get()->every(fn ($item) => $item->is_bringing && $item->is_taking && $item->other_notes === 'Leave at stage door.'));
        $this->putJson($url, ['item_code' => 'video_1', 'is_bringing' => false, 'is_taking' => false, 'other_notes' => null])->assertOk();
        $this->assertSame(6, $assignment->equipmentResponsibilities()->count());
        $this->putJson($url, ['item_code' => 'unknown', 'is_bringing' => true, 'is_taking' => false])->assertUnprocessable();
        $this->actingAs($assignment->crewProfile->user)->putJson($url, ['item_code' => 'video_1', 'is_bringing' => true, 'is_taking' => false])->assertForbidden();
    }

    public function test_setup_and_set_down_indicators_follow_kit_responsibilities_for_their_own_shift(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        foreach (['concert', 'competition'] as $type) {
            [$assignment, $event] = $this->assignment('Coverage '.$type, 12, 'Kit crew');
            $event->update(['event_type' => $type]);
            $assignment->shift->update(['requires_setup' => true, 'requires_set_down' => true]);
            $otherShift = $event->shifts()->create(['shift_date' => $event->event_date, 'requires_setup' => true, 'requires_set_down' => true]);
            $url = route('admin.scheduling-events.index', ['search' => $event->name]);
            $equipmentUrl = route('admin.scheduling-assignments.equipment', $assignment);
            $assertCoverage = function (bool $setup, bool $setDown) use ($url, $assignment, $otherShift): void {
                $this->get($url)->assertOk()->assertViewHas('equipmentShiftCoverage', function ($coverage) use ($setup, $setDown, $assignment, $otherShift): bool {
                    return $coverage[$assignment->scheduling_shift_id] === ['setup' => $setup, 'set_down' => $setDown]
                        && $coverage[$otherShift->id] === ['setup' => false, 'set_down' => false];
                });
            };

            $assertCoverage(false, false);
            $this->putJson($equipmentUrl, ['item_code' => 'photos', 'is_bringing' => true, 'is_taking' => true])->assertOk();
            $assertCoverage(false, false);
            $this->putJson($equipmentUrl, ['item_code' => 'video_1', 'is_bringing' => true, 'is_taking' => false])->assertOk();
            $assertCoverage(true, false);
            $this->get($url)->assertSee('class="mini-badge equipment-covered" title="Setup assigned:', false);
            $this->putJson($equipmentUrl, ['item_code' => 'backdrop_2', 'is_bringing' => false, 'is_taking' => true])->assertOk();
            $assertCoverage(true, true);
            $this->get($url)->assertSee('class="mini-badge equipment-covered" title="Set down assigned:', false);
            $this->putJson($equipmentUrl, ['item_code' => 'video_1', 'is_bringing' => false, 'is_taking' => false])->assertOk();
            $assertCoverage(false, true);
            $assignment->delete();
            $assertCoverage(false, false);
        }
    }

    public function test_shift_requirements_save_independently_and_notify_only_affected_published_crew(): void
    {
        [$assignment, $event] = $this->assignment('Junior concert', 10, 'Assigned crew');
        $shift = $assignment->shift;
        $shift->update(['requires_setup' => true, 'requires_set_down' => true]);
        $times = $shift->only(['posted_arrival_at', 'starts_at', 'estimated_finish_at']);
        $assignment->update(['status' => 'published', 'acknowledgement_status' => 'acknowledged', 'acknowledged_at' => now()]);
        $otherShift = $event->shifts()->create(['shift_date' => $event->event_date, 'requires_setup' => true, 'requires_set_down' => true]);
        $otherAssignment = $otherShift->assignments()->create(['crew_profile_id' => $assignment->crew_profile_id, 'crew_role_id' => $assignment->crew_role_id, 'status' => 'published', 'acknowledgement_status' => 'acknowledged']);
        $url = route('admin.scheduling-shifts.requirements', $shift);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('Setup &amp; set down', false);
        $this->putJson($url, ['requires_setup' => true, 'requires_set_down' => false])->assertOk();
        $this->assertTrue($shift->fresh()->requires_setup);
        $this->assertFalse($shift->fresh()->requires_set_down);
        $this->assertEquals($times, $shift->fresh()->only(array_keys($times)));
        $this->assertTrue($otherShift->fresh()->requires_set_down);
        $this->assertSame('reset_by_material_change', $assignment->fresh()->acknowledgement_status);
        $this->assertSame('acknowledged', $otherAssignment->fresh()->acknowledgement_status);
        $this->assertDatabaseCount('crew_notifications', 1);
        $this->putJson($url, ['requires_setup' => true, 'requires_set_down' => false])->assertOk();
        $this->assertDatabaseCount('crew_notifications', 1);
        $this->putJson($url, ['requires_setup' => false, 'requires_set_down' => false])->assertOk();
        $this->assertFalse($shift->fresh()->requires_setup);
        $this->putJson($url, ['requires_setup' => 'invalid'])->assertUnprocessable();
        $this->actingAs($assignment->crewProfile->user)->putJson($url, ['requires_setup' => true, 'requires_set_down' => true])->assertForbidden();

        $event->update(['event_type' => 'competition']);
        $this->actingAs(User::factory()->staff()->create())->putJson($url, ['requires_setup' => true, 'requires_set_down' => true])->assertOk();
        $this->assertTrue($shift->fresh()->requires_setup);
        $this->assertTrue($shift->fresh()->requires_set_down);
    }

    public function test_photo_and_video_media_are_independent_and_share_one_description(): void
    {
        [$assignment, $event] = $this->assignment('Separate media', 10, 'Media crew');
        $event->roleRequirements()->create(['crew_role_id' => $assignment->crew_role_id, 'quantity' => 1]);
        $this->actingAs(User::factory()->staff()->create());
        $url = route('admin.scheduling-assignments.equipment', $assignment);
        $this->putJson($url, ['item_code' => 'photos', 'is_bringing' => true, 'is_taking' => true])->assertOk();
        $this->putJson($url, ['item_code' => 'videos', 'is_bringing' => false, 'is_taking' => true])->assertOk();
        $summary = AssignmentEquipmentItems::mediaSummary($assignment->equipmentResponsibilities()->get());
        $this->assertSame('Bring photos · Take photos & videos', $summary['description']);
        $page = $this->get(route('admin.scheduling-events.index'))->assertOk();
        $page->assertSee('Bring Photos')->assertSee('Take Videos')->assertSee('Bring photos · Take photos &amp; videos', false);
        $this->assertSame(1, substr_count($page->getContent(), 'class="equipment-marker media-marker"'));
        $this->putJson($url, ['item_code' => 'photos', 'is_bringing' => false, 'is_taking' => false])->assertOk();
        $this->assertSame('Take videos', AssignmentEquipmentItems::mediaSummary($assignment->equipmentResponsibilities()->get())['description']);
        $this->putJson($url, ['item_code' => 'media', 'is_bringing' => true, 'is_taking' => true])->assertUnprocessable();
    }

    public function test_legacy_media_migration_preserves_directions_and_notes_and_can_roll_back(): void
    {
        [$assignment] = $this->assignment('Legacy media', 11, 'Legacy crew');
        $assignment->equipmentResponsibilities()->create(['item_code' => 'media', 'is_bringing' => true, 'is_taking' => false, 'other_notes' => 'Collect from office.']);
        $migration = require database_path('migrations/2026_09_29_190000_split_equipment_media_responsibilities.php');
        $migration->up();
        $items = $assignment->equipmentResponsibilities()->get();
        $this->assertSame(['photos', 'videos'], $items->pluck('item_code')->sort()->values()->all());
        $this->assertTrue($items->every(fn ($item) => $item->is_bringing && ! $item->is_taking && $item->other_notes === 'Collect from office.'));
        $migration->down();
        $this->assertSame(1, $assignment->equipmentResponsibilities()->count());
        $this->assertDatabaseHas('assignment_equipment_responsibilities', ['scheduling_shift_assignment_id' => $assignment->id, 'item_code' => 'media', 'is_bringing' => true, 'other_notes' => 'Collect from office.']);
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
