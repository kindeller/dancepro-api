<?php

namespace Tests\Feature\Operations;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\ChecklistTemplate;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_reusable_resources_venue_map_and_event_programme(): void
    {
        Storage::fake('local');
        $staff = User::factory()->staff()->create();
        $venue = Venue::query()->create(['name' => 'Example Theatre']);
        $event = SchedulingEvent::query()->create(['name' => 'Example Event', 'event_type' => 'competition', 'event_date' => now()->addMonth()]);
        $event->venue()->associate($venue)->save();

        $this->actingAs($staff)->get(route('admin.crew-management.resources'))
            ->assertOk()->assertSee('Add handbook or role resource')->assertDontSee('Add Pre-Start Checks template');
        $this->actingAs($staff)->get(route('admin.event-management.checklists'))
            ->assertOk()->assertSee('Add Pre-Start Checks template')->assertDontSee('Add handbook or role resource');

        $this->actingAs($staff)->post(route('admin.operations.resources.store'), [
            'section_number' => 3, 'title' => 'Videography', 'resource_type' => 'handbook',
            'event_type' => 'competition', 'role_code' => 'competition-videographer', 'is_active' => '1',
            'file' => UploadedFile::fake()->create('videography.pdf', 200, 'application/pdf'),
        ])->assertRedirect();
        $resource = OperationalResource::query()->firstOrFail();
        Storage::disk('local')->assertExists($resource->file_path);
        $this->assertSame('application/pdf', $resource->file_mime_type);
        $this->assertSame(Storage::disk('local')->size($resource->file_path), $resource->file_size);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($resource->file_path)), $resource->file_checksum);
        $previousResourcePath = $resource->file_path;
        $this->actingAs($staff)->put(route('admin.operations.resources.update', $resource), [
            'section_number' => 3, 'title' => 'Videography', 'resource_type' => 'handbook',
            'event_type' => 'competition', 'role_code' => 'competition-videographer', 'is_active' => '1',
            'file' => UploadedFile::fake()->create('updated-videography.pdf', 200, 'application/pdf'),
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($previousResourcePath);
        Storage::disk('local')->assertExists($resource->refresh()->file_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($resource->file_path)), $resource->file_checksum);

        $this->actingAs($staff)->put(route('admin.venues.map.update', $venue), [
            'map' => UploadedFile::fake()->image('venue.jpg', 1600, 900),
        ])->assertRedirect();
        Storage::disk('local')->assertExists($venue->refresh()->map_path);
        $previousMapPath = $venue->map_path;
        $this->actingAs($staff)->put(route('admin.venues.map.update', $venue), [
            'map' => UploadedFile::fake()->image('updated-venue.jpg', 1600, 900),
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($previousMapPath);
        Storage::disk('local')->assertExists($venue->refresh()->map_path);

        $this->actingAs($staff)->put(route('admin.scheduling-events.operations.update', $event), [
            'crew_brief' => 'Meet beside the loading dock.', 'team_leader_notes' => 'Collect the key.',
            'programme' => UploadedFile::fake()->create('programme.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('Meet beside the loading dock.', $event->refresh()->crew_brief);
        Storage::disk('local')->assertExists($event->programme_path);
        $previousProgrammePath = $event->programme_path;
        $this->actingAs($staff)->put(route('admin.scheduling-events.operations.update', $event), [
            'programme' => UploadedFile::fake()->create('updated-programme.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($previousProgrammePath);
        Storage::disk('local')->assertExists($event->refresh()->programme_path);
    }

    public function test_resources_and_pre_start_checks_can_target_a_managed_event_type(): void
    {
        $staff = User::factory()->staff()->create();
        $eventType = EventTypeDefinition::query()->create([
            'code' => 'dance-concert',
            'name' => 'Dance Concert',
            'system_category' => 'concert',
            'is_active' => true,
        ]);

        $this->actingAs($staff)->post(route('admin.operations.resources.store'), [
            'title' => 'Dance concert guide',
            'resource_type' => 'cheat_sheet',
            'event_type_definition_id' => $eventType->id,
            'is_active' => '1',
        ])->assertRedirect();
        $this->actingAs($staff)->post(route('admin.operations.checklists.store'), [
            'name' => 'Dance concert checks',
            'event_type_definition_id' => $eventType->id,
            'items' => "Test camera\nCheck audio",
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('operational_resources', [
            'event_type_definition_id' => $eventType->id,
            'event_type' => 'concert',
        ]);
        $this->assertDatabaseHas('checklist_templates', [
            'event_type_definition_id' => $eventType->id,
            'event_type' => 'concert',
        ]);
    }

    public function test_crew_sees_only_applicable_resources_and_can_immediately_save_checklist_progress(): void
    {
        $crewUser = User::factory()->crew()->create();
        $otherUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        CrewProfile::factory()->for($otherUser)->create();
        $role = CrewRole::query()->firstOrCreate(['code' => 'competition-videographer'], ['name' => 'Competition Videographer V', 'event_type' => 'competition', 'is_active' => true]);
        $venue = Venue::query()->create(['name' => 'Example Theatre', 'parking_notes' => 'Use bay three.']);
        $event = SchedulingEvent::query()->create(['name' => 'Example Competition', 'event_type' => 'competition', 'event_date' => now()->addMonth(), 'venue_id' => $venue->id, 'crew_brief' => 'Check in with the team leader.']);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);
        OperationalResource::query()->create(['title' => 'Video guide', 'resource_type' => 'cheat_sheet', 'event_type' => 'competition', 'role_code' => 'competition-videographer', 'is_active' => true]);
        OperationalResource::query()->create(['title' => 'Photo only guide', 'resource_type' => 'cheat_sheet', 'event_type' => 'competition', 'role_code' => 'photographer', 'is_active' => true]);
        $template = ChecklistTemplate::query()->create(['name' => 'Video pre-start', 'event_type' => 'competition', 'role_code' => 'competition-videographer', 'is_active' => true]);
        $item = $template->items()->create(['instruction' => 'Record and review a test clip.', 'sort_order' => 1]);

        $this->actingAs($crewUser)->get(route('crew.assignments.show', $assignment))
            ->assertOk()->assertSee('Check in with the team leader.')->assertSee('Video guide')->assertDontSee('Photo only guide')->assertSee('Record and review a test clip.');
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()->assertSee('Open Pre-Start Checks')->assertSee('0 of 1 complete');
        $this->actingAs($crewUser)->putJson(route('crew.assignments.checklist.toggle', [$assignment, $item]), ['completed' => true])->assertOk();
        $this->assertDatabaseHas('assignment_checklist_completions', ['scheduling_shift_assignment_id' => $assignment->id, 'checklist_template_item_id' => $item->id, 'completed_by_user_id' => $crewUser->id]);
        $this->actingAs($crewUser)->get(route('crew.availability.index', ['view' => 'upcoming']))
            ->assertOk()->assertSee('Checks completed')->assertSee('Open Pre-Start Checks');
        $this->actingAs($otherUser)->get(route('crew.assignments.show', $assignment))->assertForbidden();
    }

    public function test_stage_and_portrait_photographers_receive_different_operational_materials(): void
    {
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $event = SchedulingEvent::query()->create(['name' => 'Concert', 'event_type' => 'concert', 'event_date' => now()->addMonth()]);
        $stageShift = $event->shifts()->create(['shift_date' => $event->event_date]);
        $portraitShift = $event->shifts()->create(['shift_date' => $event->event_date]);
        $stageRole = CrewRole::query()->firstOrCreate(['code' => 'concert-photographer-p1'], ['name' => 'Concert Photographer P1', 'event_type' => 'concert', 'is_active' => true]);
        $portraitRole = CrewRole::query()->firstOrCreate(['code' => 'photographer-p'], ['name' => 'Dress Rehearsal Photographer P', 'event_type' => 'concert', 'is_active' => true]);
        $stageAssignment = $stageShift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $stageRole->id, 'status' => 'published']);
        $portraitAssignment = $portraitShift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $portraitRole->id, 'status' => 'published']);
        OperationalResource::query()->create(['title' => 'Stage photography guide', 'resource_type' => 'cheat_sheet', 'event_type' => 'concert', 'role_code' => 'concert-photographer-p1', 'is_active' => true]);
        OperationalResource::query()->create(['title' => 'Portrait photography guide', 'resource_type' => 'cheat_sheet', 'event_type' => 'concert', 'role_code' => 'photographer-p', 'is_active' => true]);

        $this->actingAs($crewUser)->get(route('crew.assignments.show', $stageAssignment))
            ->assertOk()->assertSee('Stage photography guide')->assertDontSee('Portrait photography guide');
        $this->actingAs($crewUser)->get(route('crew.assignments.show', $portraitAssignment))
            ->assertOk()->assertSee('Portrait photography guide')->assertDontSee('Stage photography guide');
    }
}
