<?php

namespace Tests\Feature\Operations;

use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_assigned_event_chat_appears_seven_days_before_event_without_messages(): void
    {
        [$crew, $event] = $this->assignedEvent(now()->addDays(7));
        [, $laterEvent] = $this->assignedEvent(now()->addDays(8), $crew);

        $this->actingAs($crew)->get(route('crew.chat.index', ['filter' => 'upcoming']))
            ->assertOk()->assertSee($event->name)->assertDontSee($laterEvent->name)->assertSee('Event chat is ready');
    }

    public function test_event_chat_is_limited_to_assigned_crew_and_opening_marks_messages_read(): void
    {
        [$crew, $event] = $this->assignedEvent(now()->addDays(3));
        $author = User::factory()->staff()->create();
        $message = EventMessage::query()->create(['scheduling_event_id' => $event->id, 'author_user_id' => $author->id, 'message_type' => 'discussion', 'body' => 'Meet at the loading dock.']);

        $this->actingAs($crew)->get(route('crew.chat.index', ['filter' => 'unread']))->assertOk()->assertSee($event->name)->assertSee('unread-count');
        $this->actingAs($crew)->get(route('crew.chat.event', $event))->assertOk()->assertSee('Meet at the loading dock.');
        $this->assertDatabaseHas('event_message_reads', ['event_message_id' => $message->id, 'user_id' => $crew->id]);

        $other = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($other)->create();
        $this->actingAs($other)->get(route('crew.chat.event', $event))->assertNotFound();
    }

    public function test_crew_can_start_and_use_a_private_direct_chat(): void
    {
        $sender = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($sender)->create(['preferred_name' => 'Alex']);
        $recipient = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        $recipientProfile = CrewProfile::factory()->for($recipient)->create(['preferred_name' => 'Jess']);

        $response = $this->actingAs($sender)->post(route('crew.chat.start'), ['recipient_profile_uuid' => $recipientProfile->uuid]);
        $conversation = DirectChatConversation::query()->firstOrFail();
        $response->assertRedirect(route('crew.chat.direct', $conversation));
        $this->assertCount(2, $conversation->participants);

        $this->actingAs($sender)->post(route('crew.chat.direct.store', $conversation), ['body' => 'Can you bring Video 2?'])->assertRedirect(route('crew.chat.direct', $conversation));
        $this->actingAs($recipient)->get(route('crew.chat.index', ['filter' => 'unread']))->assertOk()->assertSee('Alex')->assertSee('Can you bring Video 2?');
        $this->actingAs($recipient)->get(route('crew.chat.direct', $conversation))->assertOk()->assertSee('Can you bring Video 2?');

        $outsider = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($outsider)->create();
        $this->actingAs($outsider)->get(route('crew.chat.direct', $conversation))->assertForbidden();
    }

    private function assignedEvent($date, ?User $crew = null): array
    {
        $crew ??= User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        $profile = CrewProfile::query()->where('user_id', $crew->id)->first()
            ?: CrewProfile::factory()->for($crew)->create();
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create(['name' => 'Event '.$date->format('Ymd'), 'event_type' => 'competition', 'event_date' => $date]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $date]);
        $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);

        return [$crew, $event];
    }
}
