<?php

namespace Tests\Feature\Operations;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_documents_require_authentication(): void
    {
        $resource = OperationalResource::query()->create([
            'title' => 'Crew handbook',
            'resource_type' => 'handbook',
            'file_path' => 'operations/resources/handbook.pdf',
            'is_active' => true,
        ]);

        $this->get(route('internal-documents.resources.show', $resource))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_open_all_internal_document_types(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        [$resource, $venue, $event, $message] = $this->documents();

        $this->actingAs($admin)->get($resource->fileUrl())->assertOk();
        $this->actingAs($admin)->get($venue->mapUrl())->assertOk();
        $this->actingAs($admin)->get($event->programmeUrl())->assertOk();
        $this->actingAs($admin)->get($message->attachmentUrl())->assertOk();
    }

    public function test_crew_can_only_open_active_resources_and_documents_for_assigned_events(): void
    {
        Storage::fake('local');
        $crew = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($crew)->create();
        [$resource, $venue, $event, $message] = $this->documents();
        $shift = $event->shifts()->create(['shift_date' => $event->event_date]);
        $shift->assignments()->create([
            'crew_profile_id' => $profile->id,
            'crew_role_id' => CrewRole::factory()->create()->id,
            'status' => 'published',
        ]);

        $this->actingAs($crew)->get($resource->fileUrl())->assertOk();
        $this->actingAs($crew)->get($venue->mapUrl())->assertOk();
        $this->actingAs($crew)->get($event->programmeUrl())->assertOk();
        $this->actingAs($crew)->get($message->attachmentUrl())->assertOk();

        $resource->update(['is_active' => false]);
        $this->actingAs($crew)->get($resource->fileUrl())->assertForbidden();

        $otherEvent = SchedulingEvent::query()->create([
            'name' => 'Other event',
            'event_type' => 'competition',
            'event_date' => now()->addMonths(2),
            'programme_path' => 'operations/events/other/programme.pdf',
        ]);
        Storage::disk('local')->put($otherEvent->programme_path, 'private');
        $this->actingAs($crew)->get($otherEvent->programmeUrl())->assertForbidden();
    }

    public function test_customer_cannot_open_internal_documents(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        [$resource] = $this->documents();

        $this->actingAs($customer)->get($resource->fileUrl())->assertForbidden();
    }

    private function documents(): array
    {
        $venue = Venue::query()->create(['name' => 'Theatre', 'map_path' => 'operations/venues/map.jpg']);
        $event = SchedulingEvent::query()->create([
            'name' => 'Competition',
            'event_type' => 'competition',
            'event_date' => now()->addMonth(),
            'venue_id' => $venue->id,
            'programme_path' => 'operations/events/programme.pdf',
        ]);
        $resource = OperationalResource::query()->create([
            'title' => 'Crew handbook',
            'resource_type' => 'handbook',
            'file_path' => 'operations/resources/handbook.pdf',
            'is_active' => true,
        ]);
        $message = EventMessage::query()->create([
            'scheduling_event_id' => $event->id,
            'author_user_id' => User::factory()->admin()->create()->id,
            'message_type' => 'discussion',
            'attachment_path' => 'event-communications/attachment.pdf',
            'attachment_name' => 'attachment.pdf',
        ]);

        foreach ([$venue->map_path, $event->programme_path, $resource->file_path, $message->attachment_path] as $path) {
            Storage::disk('local')->put($path, 'private');
        }

        return [$resource, $venue, $event, $message];
    }
}
