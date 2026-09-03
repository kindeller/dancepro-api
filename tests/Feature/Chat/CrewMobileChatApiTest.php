<?php

namespace Tests\Feature\Chat;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Chat\Actions\StartDirectChat;
use App\Features\Chat\Models\DirectChatMessage;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crew_can_list_read_and_post_to_an_assigned_event_chat(): void
    {
        [$crew, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $author = User::factory()->staff()->create();
        $message = EventMessage::query()->create([
            'scheduling_event_id' => $event->id,
            'author_user_id' => $author->id,
            'message_type' => 'discussion',
            'body' => 'Meet at the loading dock.',
        ]);

        $this->getJson('/api/v1/chats')->assertOk()
            ->assertJsonPath('data.0.id', $event->uuid)
            ->assertJsonPath('data.0.unread_count', 1);
        $this->getJson('/api/v1/chats/'.$event->uuid.'/messages')->assertOk()
            ->assertJsonPath('data.0.id', $message->uuid);
        $this->putJson('/api/v1/chats/'.$event->uuid.'/read', ['through_message' => $message->uuid])->assertOk();
        $this->assertDatabaseHas('event_message_reads', ['event_message_id' => $message->id, 'user_id' => $crew->id]);

        $key = (string) Str::uuid();
        $firstPost = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages', ['body' => 'Thanks, understood.'])
            ->assertCreated()->assertJsonPath('data.body', 'Thanks, understood.');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages', ['body' => 'Thanks, understood.'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true')
            ->assertExactJson($firstPost->json());
        $this->assertSame(2, $event->messages()->count());

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages', ['body' => 'A different message.'])
            ->assertConflict();
        $this->assertSame(2, $event->messages()->count());
    }

    public function test_direct_chat_is_private_and_supports_message_read_state(): void
    {
        [$sender] = $this->authenticatedCrew();
        $recipient = User::factory()->crew()->create();
        CrewProfile::factory()->for($recipient)->create();
        $conversation = app(StartDirectChat::class)->execute($sender, $recipient);
        $message = DirectChatMessage::query()->create([
            'direct_chat_conversation_id' => $conversation->id,
            'author_user_id' => $recipient->id,
            'body' => 'Can you bring Video 2?',
        ]);

        $this->getJson('/api/v1/chats/'.$conversation->uuid.'/messages')->assertOk()
            ->assertJsonPath('data.0.body', 'Can you bring Video 2?');
        $this->putJson('/api/v1/chats/'.$conversation->uuid.'/read', ['through_message' => $message->uuid])->assertOk();

        [$outsider] = $this->authenticatedCrew();
        $this->getJson('/api/v1/chats/'.$conversation->uuid.'/messages')->assertNotFound();
    }

    public function test_crew_can_start_an_idempotent_direct_chat_from_a_directory_profile(): void
    {
        [$sender] = $this->authenticatedCrew();
        $recipient = User::factory()->crew()->create();
        $recipientProfile = CrewProfile::factory()->for($recipient)->create(['preferred_name' => 'Morgan']);

        $first = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/direct', ['recipient_profile_uuid' => $recipientProfile->uuid])
            ->assertOk()
            ->assertJsonPath('data.kind', 'direct')
            ->assertJsonPath('data.title', 'Morgan');

        $second = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/direct', ['recipient_profile_uuid' => $recipientProfile->uuid])
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('direct_chat_conversations', 1);
        $this->assertDatabaseHas('direct_chat_participants', ['user_id' => $sender->id]);
        $this->assertDatabaseHas('direct_chat_participants', ['user_id' => $recipient->id]);
    }

    public function test_crew_cannot_start_a_direct_chat_with_themselves_or_inactive_crew(): void
    {
        [$sender, $senderProfile] = $this->authenticatedCrew();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/direct', ['recipient_profile_uuid' => $senderProfile->uuid])
            ->assertUnprocessable();

        $inactive = User::factory()->crew()->create(['is_active' => false]);
        $inactiveProfile = CrewProfile::factory()->for($inactive)->create();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/direct', ['recipient_profile_uuid' => $inactiveProfile->uuid])
            ->assertUnprocessable();
    }

    public function test_notifications_are_cursor_paginated_and_limited_to_current_user(): void
    {
        [$crew] = $this->authenticatedCrew();
        $other = User::factory()->crew()->create();
        CrewNotification::query()->create(['user_id' => $crew->id, 'type' => 'shift', 'title' => 'Your shift', 'message' => 'Review it.']);
        CrewNotification::query()->create(['user_id' => $other->id, 'type' => 'shift', 'title' => 'Private', 'message' => 'Not yours.']);

        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Your shift')
            ->assertJsonMissing(['title' => 'Private']);
    }

    public function test_crew_can_idempotently_mark_only_their_notification_as_read(): void
    {
        [$crew] = $this->authenticatedCrew();
        $other = User::factory()->crew()->create();
        $notification = CrewNotification::query()->create([
            'user_id' => $crew->id, 'type' => 'shift', 'title' => 'Your shift', 'message' => 'Review it.',
        ]);
        $privateNotification = CrewNotification::query()->create([
            'user_id' => $other->id, 'type' => 'shift', 'title' => 'Private', 'message' => 'Not yours.',
        ]);

        $this->putJson('/api/v1/notifications/'.$notification->uuid.'/read')
            ->assertOk()->assertJsonPath('message', 'Notification marked as read.');
        $readAt = $notification->fresh()->read_at;
        $this->assertNotNull($readAt);

        $this->putJson('/api/v1/notifications/'.$notification->uuid.'/read')->assertOk();
        $this->assertTrue($readAt->equalTo($notification->fresh()->read_at));

        $this->putJson('/api/v1/notifications/'.$privateNotification->uuid.'/read')->assertNotFound();
        $this->assertNull($privateNotification->fresh()->read_at);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(): array
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear()->toDateString(),
        ]);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }

    private function assignedEvent(CrewProfile $profile): SchedulingEvent
    {
        $role = CrewRole::factory()->create();
        $event = SchedulingEvent::query()->create([
            'name' => 'Mobile Event', 'event_type' => 'competition', 'event_date' => today()->addDays(3),
        ]);
        $shift = $event->shifts()->create(['period' => 'morning', 'shift_date' => $event->event_date]);
        $shift->assignments()->create(['crew_profile_id' => $profile->id, 'crew_role_id' => $role->id, 'status' => 'published']);

        return $event;
    }
}
