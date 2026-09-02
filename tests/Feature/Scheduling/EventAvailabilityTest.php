<?php

namespace Tests\Feature\Scheduling;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Scheduling\Support\ShiftPeriod;
use App\Features\Studios\Models\Studio;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_concert_logo_uses_linked_studio_even_when_event_name_changes(): void
    {
        Storage::fake('public');
        $studio = Studio::factory()->create(['name' => 'Original Studio', 'logo_path' => 'logos/studios/original/logo.jpg']);
        Storage::disk('public')->put($studio->logo_path, 'logo');
        $user = User::factory()->crew()->create();
        CrewProfile::factory()->for($user)->create();
        $event = SchedulingEvent::query()->create([
            'studio_id' => $studio->id,
            'name' => 'Renamed End of Year Concert',
            'event_type' => 'concert',
            'event_date' => now()->addMonth(),
            'availability_status' => 'open',
            'availability_deadline' => now()->addWeek(),
        ]);
        $event->shifts()->create(['shift_date' => $event->event_date]);

        $this->actingAs($user)->get(route('crew.availability.index'))
            ->assertOk()
            ->assertSee($studio->logoUrl(), false);
    }

    public function test_administrator_creates_separate_morning_and_afternoon_shifts(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $venue = Venue::query()->create(['name' => 'Fictional Theatre']);

        $this->actingAs($staff)->post(route('admin.scheduling-events.store'), [
            'name' => 'Perth Dance Festival', 'venue_id' => $venue->id, 'event_type' => 'competition',
            'organiser_name' => 'Taylor Organiser', 'organiser_email' => 'taylor@example.test', 'organiser_phone' => '0400 111 222',
            'logo' => UploadedFile::fake()->image('competition-logo.jpg', 3508, 2480),
            'roles' => ['competition-videographer', 'competition-photographer-p1', 'competition-photographer-p2'],
            'days' => [
                ['date' => now()->addMonth()->toDateString(), 'morning' => '1', 'afternoon' => '1', 'setup_period' => 'morning', 'set_down_period' => 'afternoon'],
            ],
        ])->assertRedirect();

        $event = SchedulingEvent::query()->firstOrFail();
        Storage::disk('public')->assertExists($event->logo_path);
        $this->assertEqualsCanonicalizing([ShiftPeriod::Morning, ShiftPeriod::Afternoon], $event->shifts()->pluck('period')->all());
        $this->assertDatabaseCount('scheduling_shifts', 2);
        $this->assertSame('draft', $event->availability_status->value);
        $this->assertDatabaseCount('scheduling_event_role_requirements', 3);
        $morning = $event->shifts()->where('period', 'morning')->firstOrFail();
        $afternoon = $event->shifts()->where('period', 'afternoon')->firstOrFail();
        $this->assertNull($morning->starts_at);

        $this->actingAs($staff)->patch(route('admin.scheduling-events.shifts.times', [$event, $morning]), ['start_time' => '08:00', 'finish_time' => '12:00'])->assertRedirect();
        $this->actingAs($staff)->patch(route('admin.scheduling-events.shifts.times', [$event, $afternoon]), ['start_time' => '13:00', 'finish_time' => '17:00'])->assertRedirect();
        $this->assertSame('06:30', $morning->refresh()->posted_arrival_at->format('H:i'));
        $this->assertSame('12:30', $afternoon->refresh()->posted_arrival_at->format('H:i'));
        $this->assertSame('17:20', $afternoon->estimated_finish_at->format('H:i'));

        $this->actingAs($staff)->patch(route('admin.scheduling-events.availability', $event), ['availability_status' => 'open', 'availability_deadline' => now()->addWeek()->toDateString()])->assertRedirect();
        $this->assertSame('open', $event->refresh()->availability_status->value);
        $this->assertSame('17:00', $event->availability_deadline->format('H:i'));
        $this->actingAs($staff)->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('Perth Dance Festival');
        $this->actingAs($staff)->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('COMP-M')->assertSee('COMP-A');
        $this->actingAs($staff)->get(route('admin.scheduling-events.show', $event))->assertOk()->assertSee('Morning')->assertSee('Afternoon');
    }

    public function test_each_competition_day_requires_morning_or_afternoon(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->from(route('admin.scheduling-events.create'))->post(route('admin.scheduling-events.store'), [
            'name' => 'Invalid Empty Day', 'event_type' => 'competition', 'roles' => ['competition-videographer'],
            'organiser_name' => 'Taylor Organiser', 'organiser_email' => 'taylor@example.test', 'organiser_phone' => '0400 111 222',
            'days' => [['date' => now()->addMonth()->toDateString(), 'morning' => '0', 'afternoon' => '0']],
        ])->assertRedirect(route('admin.scheduling-events.create'))->assertSessionHasErrors('days.0.morning');

        $this->assertDatabaseCount('scheduling_events', 0);
    }

    public function test_crew_member_can_respond_to_morning_and_afternoon_independently(): void
    {
        $user = User::factory()->crew()->create();
        $crewProfile = CrewProfile::factory()->for($user)->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Perth Dance Festival', 'event_type' => 'competition', 'event_date' => now()->addMonth(),
            'availability_status' => 'open', 'availability_deadline' => now()->addWeek(),
        ]);
        $morning = $event->shifts()->create(['period' => 'morning']);
        $afternoon = $event->shifts()->create(['period' => 'afternoon']);

        $this->actingAs($user)->get(route('crew.availability.index'))->assertOk()->assertSee('Morning')->assertSee('Afternoon');
        $this->actingAs($user)->put(route('crew.availability.store', $morning), ['status' => 'available', 'note' => 'Can arrive early.'])->assertRedirect();
        $this->actingAs($user)->put(route('crew.availability.store', $afternoon), ['status' => 'unavailable', 'note' => 'Family commitment.'])->assertRedirect();

        $this->assertDatabaseHas('crew_availability_responses', ['crew_profile_id' => $crewProfile->id, 'scheduling_shift_id' => $morning->id, 'status' => 'available']);
        $this->assertDatabaseHas('crew_availability_responses', ['crew_profile_id' => $crewProfile->id, 'scheduling_shift_id' => $afternoon->id, 'status' => 'unavailable']);
        $this->actingAs($user)->get(route('crew.availability.index'))->assertOk()->assertSee('Assigned shifts')->assertDontSee('Availability requests');
    }

    public function test_crew_account_cannot_open_administrator_events_or_private_profiles(): void
    {
        $user = User::factory()->crew()->create();
        CrewProfile::factory()->for($user)->create();

        $this->actingAs($user)->get(route('admin.scheduling-events.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.crew.index'))->assertForbidden();
    }

    public function test_administrator_can_update_matrix_availability_and_event_assignment(): void
    {
        $staff = User::factory()->staff()->create();
        $crewUser = User::factory()->crew()->create(['name' => 'Alex Matrix']);
        $profile = CrewProfile::factory()->for($crewUser)->create(['preferred_name' => 'Alex']);
        $role = CrewRole::factory()->create(['code' => 'matrix-video', 'name' => 'Matrix Videographer']);
        $profile->roles()->attach($role->id, ['status' => 'approved']);
        $event = SchedulingEvent::query()->create(['name' => 'Matrix Event', 'event_type' => 'competition', 'event_date' => now()->addMonth(), 'availability_status' => 'open', 'availability_deadline' => now()->addWeek()->setTime(17, 0)]);
        $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);

        $this->actingAs($staff)->putJson(route('admin.scheduling-shifts.crew.availability', [$shift, $profile]), ['status' => 'available'])->assertOk();
        $this->actingAs($staff)->putJson(route('admin.scheduling-shifts.roles.assignment', [$shift, $role]), ['crew_profile_uuid' => $profile->uuid])->assertOk();

        $this->assertDatabaseHas('crew_availability_responses', ['scheduling_shift_id' => $shift->id, 'crew_profile_id' => $profile->id, 'status' => 'available']);
        $this->assertDatabaseHas('scheduling_shift_assignments', ['scheduling_shift_id' => $shift->id, 'crew_role_id' => $role->id, 'crew_profile_id' => $profile->id, 'status' => 'draft']);
        $this->actingAs($staff)->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('Matrix Event')->assertSee('Alex')->assertSee('Roster key')->assertSee('✏️')->assertSee('Role 1')->assertSee('Matrix Videographer')->assertSee('reloadSpreadsheetInPlace', false);
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'availability']))->assertOk()->assertSee('Matrix Event')->assertDontSee('Allocated');
    }

    public function test_published_assignment_is_notified_acknowledged_and_locks_availability(): void
    {
        $staff = User::factory()->staff()->create();
        $crewUser = User::factory()->crew()->create(['name' => 'Jess Roster']);
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::factory()->create(['code' => 'roster-p1']);
        $profile->roles()->attach($role->id, ['status' => 'approved']);
        $event = SchedulingEvent::query()->create(['name' => 'Published Roster', 'event_type' => 'competition', 'event_date' => now()->addMonth(), 'availability_status' => 'closed']);
        $event->roleRequirements()->create(['crew_role_id' => $role->id]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);
        $response = $shift->availabilityResponses()->create(['crew_profile_id' => $profile->id, 'status' => 'available', 'responded_at' => now()]);
        $this->actingAs($staff)->putJson(route('admin.scheduling-shifts.roles.assignment', [$shift, $role]), ['crew_profile_uuid' => $profile->uuid])->assertOk();
        $this->actingAs($staff)->putJson(route('admin.scheduling-shifts.crew.team-leader', [$shift, $profile]), ['is_team_leader' => true])->assertOk();
        $draftAssignment = $shift->assignments()->firstOrFail();
        $this->actingAs($staff)->putJson(route('admin.scheduling-assignments.equipment', $draftAssignment), ['item_code' => 'media', 'is_bringing' => true, 'is_taking' => true, 'other_notes' => 'David will collect it later.'])->assertOk();
        $this->assertDatabaseHas('assignment_equipment_responsibilities', ['scheduling_shift_assignment_id' => $draftAssignment->id, 'item_code' => 'media', 'is_bringing' => true, 'is_taking' => true, 'other_notes' => 'David will collect it later.']);
        $this->actingAs($staff)->putJson(route('admin.scheduling-assignments.equipment', $draftAssignment), ['item_code' => 'media', 'is_bringing' => false, 'is_taking' => false, 'other_notes' => 'Left at venue overnight.'])->assertOk();
        $this->assertDatabaseHas('assignment_equipment_responsibilities', ['scheduling_shift_assignment_id' => $draftAssignment->id, 'item_code' => 'media', 'is_bringing' => false, 'is_taking' => false, 'other_notes' => 'Left at venue overnight.']);

        $this->actingAs($staff)->post(route('admin.scheduling-events.roster.publish', $event))->assertRedirect();
        $assignment = $shift->assignments()->firstOrFail();
        $this->assertSame('published', $assignment->status);
        $this->assertTrue($assignment->is_team_leader);
        $this->actingAs($staff)->get(route('admin.scheduling-events.index'))->assertOk()->assertSee('Assigned')->assertSee('event-select-cell', false);
        $this->assertNotNull($response->refresh()->locked_at);
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $crewUser->id, 'type' => 'shift_allocation']);
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'acknowledge']))
            ->assertOk()
            ->assertSee('My shifts')
            ->assertSee('Published Roster')
            ->assertSee('Acknowledge')
            ->assertSee('unread-dot', false)
            ->assertSee('Left at venue overnight.');
        $notification = CrewNotification::query()->where('user_id', $crewUser->id)->firstOrFail();
        $this->actingAs($crewUser)->patchJson(route('crew.notifications.read', $notification))->assertOk();
        $this->assertNotNull($notification->refresh()->read_at);
        $otherCrew = User::factory()->crew()->create();
        CrewProfile::factory()->for($otherCrew)->create();
        $this->actingAs($otherCrew)->patchJson(route('crew.notifications.read', $notification))->assertForbidden();
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()->assertSee('Calendar view');
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'calendar']))
            ->assertOk()->assertSee('List view')->assertSee('data-calendar-date', false)->assertSee('Published Roster');
        $this->actingAs($crewUser)->put(route('crew.availability.store', $shift), ['status' => 'unavailable'])->assertSessionHasErrors('availability');
        $this->actingAs($crewUser)->post(route('crew.assignments.acknowledge', $assignment))->assertRedirect();
        $this->assertSame('acknowledged', $assignment->refresh()->acknowledgement_status);
        $this->assertTrue($event->refresh()->rosterIsReady());

        $this->actingAs($staff)->putJson(route('admin.scheduling-assignments.equipment', $assignment), ['item_code' => 'media', 'is_bringing' => true, 'is_taking' => false])->assertOk();
        $this->assertSame('reset_by_material_change', $assignment->refresh()->acknowledgement_status);
        $this->assertFalse($event->refresh()->rosterIsReady());

        $this->actingAs($staff)->patch(route('admin.scheduling-events.shifts.times', [$event, $shift]), ['start_time' => '08:00', 'finish_time' => '12:00'])->assertRedirect();
        $this->assertSame('reset_by_material_change', $assignment->refresh()->acknowledgement_status);
    }

    public function test_closed_or_expired_availability_cannot_be_changed(): void
    {
        $user = User::factory()->crew()->create();
        CrewProfile::factory()->for($user)->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Closed Event', 'event_type' => 'competition', 'event_date' => now()->addMonth(),
            'availability_status' => 'closed', 'availability_deadline' => now()->subHour(),
        ]);
        $shift = $event->shifts()->create(['period' => 'morning']);

        $this->actingAs($user)->put(route('crew.availability.store', $shift), ['status' => 'available'])->assertSessionHasErrors('availability');
        $this->assertDatabaseCount('crew_availability_responses', 0);
    }
}
