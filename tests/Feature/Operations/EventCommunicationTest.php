<?php

namespace Tests\Feature\Operations;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_post_an_announcement_for_assigned_crew_to_acknowledge(): void
    {
        Storage::fake('local');
        $staff = User::factory()->staff()->create();
        [$crewUser, $assignment, $event] = $this->publishedAssignment();
        $unassignedCrew = User::factory()->crew()->create();
        CrewProfile::factory()->for($unassignedCrew)->create();

        $this->actingAs($staff)->post(route('admin.scheduling-events.messages.store', $event), [
            'message_type' => 'announcement',
            'body' => 'The loading dock entrance has changed.',
            'attachment' => UploadedFile::fake()->image('new-map.jpg'),
        ])->assertRedirect();

        $message = EventMessage::query()->firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertDatabaseHas('crew_notifications', ['user_id' => $crewUser->id, 'type' => 'event_announcement']);
        $this->assertDatabaseMissing('crew_notifications', ['user_id' => $unassignedCrew->id, 'type' => 'event_announcement']);

        $this->actingAs($crewUser)->get(route('crew.assignments.show', $assignment))
            ->assertOk()->assertSee('The loading dock entrance has changed.')->assertSee('Acknowledge announcement');
        $this->actingAs($crewUser)->post(route('crew.assignments.messages.acknowledge', [$assignment, $message]))->assertRedirect();
        $this->assertDatabaseHas('event_message_reads', ['event_message_id' => $message->id, 'user_id' => $crewUser->id]);
    }

    public function test_assigned_crew_can_post_discussion_but_cannot_post_announcements(): void
    {
        [$crewUser, $assignment] = $this->publishedAssignment();

        $this->actingAs($crewUser)->post(route('crew.assignments.messages.store', $assignment), [
            'message_type' => 'discussion',
            'body' => 'Which entrance should I use?',
        ])->assertRedirect();
        $this->assertDatabaseHas('event_messages', ['author_user_id' => $crewUser->id, 'message_type' => 'discussion']);

        $this->actingAs($crewUser)->post(route('crew.assignments.messages.store', $assignment), [
            'message_type' => 'announcement',
            'body' => 'Not permitted.',
        ])->assertForbidden();
    }

    public function test_unassigned_crew_cannot_access_an_event_conversation(): void
    {
        [, $assignment] = $this->publishedAssignment();
        $otherCrew = User::factory()->crew()->create();
        CrewProfile::factory()->for($otherCrew)->create();

        $this->actingAs($otherCrew)->get(route('crew.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($otherCrew)->post(route('crew.assignments.messages.store', $assignment), [
            'message_type' => 'discussion',
            'body' => 'I should not be here.',
        ])->assertForbidden();
    }

    private function publishedAssignment(): array
    {
        $crewUser = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crewUser)->create();
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create(['name' => 'Example Event', 'event_type' => 'competition', 'event_date' => now()->addMonth()]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);
        $assignment = $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);

        return [$crewUser, $assignment, $event];
    }
}
