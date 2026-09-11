<?php

namespace Tests\Feature\Chat;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Chat\Actions\PostDirectChatMessage;
use App\Features\Chat\Actions\StartDirectChat;
use App\Features\Chat\Models\DirectChatMessage;
use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRole;
use App\Features\Notifications\Jobs\DeliverMobilePushNotification;
use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CrewMobileChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reactions_are_shared_replaceable_removable_and_authorized(): void
    {
        [$crew, $profile] = $this->authenticatedCrew();
        $crew->update(['onboarding_completed_at' => now()]);
        [$other] = $this->authenticatedCrew();
        Sanctum::actingAs($crew, [TokenAbility::CrewMobile->value]);
        $direct = app(StartDirectChat::class)->execute($crew, $other);
        $event = $this->assignedEvent($profile);
        foreach ([$event, $direct] as $chat) {
            $message = $chat->messages()->create(['author_user_id' => $other->id, 'body' => 'Reaction fixture', ...($chat === $event ? ['message_type' => 'discussion'] : [])]);
            $url = '/api/v1/chats/'.$chat->uuid.'/messages/'.$message->uuid.'/reaction';
            foreach (['thumbs_up', 'thumbs_up', 'heart', 'laugh', 'angry'] as $reaction) {
                $this->putJson($url, ['reaction' => $reaction])->assertOk()
                    ->assertJsonCount(1, 'data.reactions')->assertJsonPath('data.reactions.0.reaction', $reaction)
                    ->assertJsonPath('data.reactions.0.count', 1)->assertJsonPath('data.reactions.0.selected', true);
            }
            $this->actingAs($crew, 'web')->putJson('/crew/chat/'.$chat->uuid.'/messages/'.$message->uuid.'/reaction', ['reaction' => 'heart'])->assertOk();
            $this->getJson('/api/v1/chats/'.$chat->uuid.'/messages')->assertOk()->assertJsonPath('data.0.reactions.0.reaction', 'heart');
            $own = $chat->messages()->create(['author_user_id' => $crew->id, 'body' => 'Own message', ...($chat === $event ? ['message_type' => 'discussion'] : [])]);
            $this->putJson('/api/v1/chats/'.$chat->uuid.'/messages/'.$own->uuid.'/reaction', ['reaction' => 'heart'])->assertForbidden();
            $this->actingAs($crew, 'web')->putJson('/crew/chat/'.$chat->uuid.'/messages/'.$own->uuid.'/reaction', ['reaction' => 'heart'])->assertForbidden();
            $this->putJson($url, ['reaction' => 'invalid'])->assertUnprocessable();
            $this->putJson($url, ['reaction' => null])->assertOk()->assertJsonCount(0, 'data.reactions');
            $this->assertSame(0, $message->reactions()->count());
            $wrongChat = $chat === $event ? $direct : $event;
            $this->putJson('/api/v1/chats/'.$wrongChat->uuid.'/messages/'.$message->uuid.'/reaction', ['reaction' => 'heart'])->assertNotFound();
        }
        [$outsider] = $this->authenticatedCrew();
        foreach ([$event, $direct] as $chat) {
            $this->putJson('/api/v1/chats/'.$chat->uuid.'/messages/'.$chat->messages()->first()->uuid.'/reaction', ['reaction' => 'heart'])->assertNotFound();
        }
    }

    public function test_mobile_event_chats_use_event_codes_and_fall_back_to_names(): void
    {
        [$crew, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $competition = CompetitionContact::query()->create([
            'name' => 'Example Dance Competition', 'code' => 'EDC',
            'organiser_name' => 'Fixture', 'organiser_email' => 'fixture@example.test',
            'organiser_phone' => '0400 000 000',
        ]);
        $event->update(['competition_contact_id' => $competition->id, 'event_date' => '2026-08-01']);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.title', 'EDC826');
        $event->update(['event_date' => '2027-11-01']);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.title', 'EDC1127');
        $studio = Studio::factory()->create(['code' => 'ABC']);
        $event->update(['event_type' => 'concert', 'studio_id' => $studio->id]);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.title', 'ABC 1/11');
        $studio->update(['code' => null]);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.title', 'Mobile Event 1/11');
        $event->update(['event_type' => 'competition']);
        $competition->update(['code' => null]);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.title', 'Mobile Event');
    }

    public function test_concert_chats_for_the_same_studio_show_their_event_dates(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $studio = Studio::factory()->create(['code' => 'K2D']);
        foreach (['2026-10-12', '2026-10-19'] as $date) {
            $event = $this->assignedEvent($profile);
            $event->update(['event_type' => 'concert', 'studio_id' => $studio->id, 'event_date' => $date]);
        }
        $response = $this->getJson('/api/v1/chats')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(['K2D 12/10', 'K2D 19/10'], array_column($response->json('data'), 'title'));
    }

    public function test_event_chat_artwork_uses_the_studio_or_competition_logo_with_fallbacks(): void
    {
        config(['uploads.public_disk' => 'public']);
        Storage::fake('public', ['url' => '/storage']);
        [, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $studio = Studio::factory()->create(['logo_path' => 'studio.png']);
        $competition = CompetitionContact::query()->create([
            'name' => 'Fixture Competition', 'logo_path' => 'competition.png',
            'organiser_name' => 'Fixture', 'organiser_email' => 'fixture@example.test',
            'organiser_phone' => '0400 000 000',
        ]);
        $event->update(['studio_id' => $studio->id, 'competition_contact_id' => $competition->id, 'logo_path' => 'event.png']);
        $this->getJson('/api/v1/chats')->assertOk()
            ->assertJsonPath('data.0.event_logo_url', url('/storage/competition.png'))
            ->assertJsonPath('data.0.event_type', 'competition');
        $event->update(['event_type' => 'concert']);
        $this->getJson('/api/v1/chats')->assertOk()
            ->assertJsonPath('data.0.event_logo_url', url('/storage/studio.png'))
            ->assertJsonPath('data.0.event_type', 'concert');
        $studio->update(['logo_path' => null]);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.event_logo_url', url('/storage/event.png'));
        $event->update(['logo_path' => null]);
        $this->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.event_logo_url', null);
    }

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

    public function test_crew_can_idempotently_acknowledge_an_announcement_in_their_event_chat(): void
    {
        [$crew, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $message = EventMessage::query()->create([
            'scheduling_event_id' => $event->id,
            'author_user_id' => User::factory()->staff()->create()->id,
            'message_type' => 'announcement',
            'body' => 'The loading dock has changed.',
        ]);

        $this->getJson('/api/v1/chats/'.$event->uuid.'/messages')->assertOk()
            ->assertJsonPath('data.0.acknowledgement_required', true)
            ->assertJsonPath('data.0.acknowledged', false);

        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages/'.$message->uuid.'/acknowledgement')
            ->assertOk()
            ->assertJsonPath('data.acknowledgement_required', true)
            ->assertJsonPath('data.acknowledged', true);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages/'.$message->uuid.'/acknowledgement')
            ->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($first->json());
        $this->assertDatabaseCount('event_message_reads', 1);
        $this->assertDatabaseHas('event_message_reads', ['event_message_id' => $message->id, 'user_id' => $crew->id]);
    }

    public function test_crew_cannot_acknowledge_discussions_direct_messages_or_unassigned_event_messages(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $discussion = EventMessage::query()->create([
            'scheduling_event_id' => $event->id,
            'author_user_id' => User::factory()->staff()->create()->id,
            'message_type' => 'discussion',
            'body' => 'General information.',
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/'.$event->uuid.'/messages/'.$discussion->uuid.'/acknowledgement')
            ->assertNotFound();

        $other = User::factory()->crew()->create();
        $otherProfile = CrewProfile::factory()->for($other)->create();
        $privateEvent = $this->assignedEvent($otherProfile);
        $announcement = EventMessage::query()->create([
            'scheduling_event_id' => $privateEvent->id,
            'author_user_id' => User::factory()->staff()->create()->id,
            'message_type' => 'announcement',
            'body' => 'Private event information.',
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/chats/'.$privateEvent->uuid.'/messages/'.$announcement->uuid.'/acknowledgement')
            ->assertNotFound();
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

    public function test_direct_chat_accepts_attachment_only_photos_and_returns_downloadable_metadata(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['operations.filesystem_disk' => 'local']);
        [$sender, $senderProfile] = $this->authenticatedCrew();
        $senderProfile->update(['preferred_name' => 'Morgan']);
        $recipient = User::factory()->crew()->create();
        CrewProfile::factory()->for($recipient)->create();
        $conversation = app(StartDirectChat::class)->execute($sender, $recipient);

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/v1/chats/'.$conversation->uuid.'/messages', [
                'attachment' => UploadedFile::fake()->image('photo.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.attachment.name', 'photo.jpg')
            ->assertJsonPath('data.attachment.mime_type', 'image/jpeg')
            ->assertJsonPath('data.attachment.size', fn ($size): bool => is_int($size) && $size > 0);

        $message = DirectChatMessage::query()->latest('id')->firstOrFail();
        $this->assertNull($message->body);
        $this->assertNotNull($message->attachment_size);
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->get($response->json('data.attachment.download_url'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=photo.jpg');
        $this->assertDatabaseHas('crew_notifications', [
            'user_id' => $recipient->id,
            'type' => 'direct_message',
            'message' => 'Morgan sent a photo',
        ]);
        Queue::assertPushed(DeliverMobilePushNotification::class);

        [$outsider] = $this->authenticatedCrew();
        $this->get($response->json('data.attachment.download_url'))->assertNotFound();
    }

    public function test_direct_chat_accepts_a_photo_with_a_message_body(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [$sender] = $this->authenticatedCrew();
        $recipient = User::factory()->crew()->create();
        CrewProfile::factory()->for($recipient)->create();
        $conversation = app(StartDirectChat::class)->execute($sender, $recipient);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/v1/chats/'.$conversation->uuid.'/messages', [
                'body' => 'Here is the photo.',
                'attachment' => UploadedFile::fake()->image('photo.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Here is the photo.')
            ->assertJsonPath('data.attachment.name', 'photo.jpg');
    }

    public function test_direct_chat_rejects_oversized_and_invalid_attachments(): void
    {
        [$sender] = $this->authenticatedCrew();
        $recipient = User::factory()->crew()->create();
        CrewProfile::factory()->for($recipient)->create();
        $conversation = app(StartDirectChat::class)->execute($sender, $recipient);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/v1/chats/'.$conversation->uuid.'/messages', [
                'attachment' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/v1/chats/'.$conversation->uuid.'/messages', [
                'attachment' => UploadedFile::fake()->createWithContent('malware.exe', 'not allowed'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('direct_chat_messages', 0);
    }

    public function test_direct_chat_attachment_is_deleted_when_message_creation_fails(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [$sender] = $this->authenticatedCrew();
        $recipient = User::factory()->crew()->create();
        CrewProfile::factory()->for($recipient)->create();
        $conversation = app(StartDirectChat::class)->execute($sender, $recipient);
        $action = \Mockery::mock(PostDirectChatMessage::class, [app(OperationsFileStorage::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $action->shouldReceive('createMessage')->once()->andThrow(new RuntimeException('Database write failed.'));

        try {
            $action->execute($conversation, $sender, null, UploadedFile::fake()->image('photo.jpg'));
            $this->fail('The simulated database failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database write failed.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('operations/chat/direct/'.$conversation->uuid));
        $this->assertDatabaseCount('direct_chat_messages', 0);
    }

    public function test_assigned_crew_can_discover_and_download_private_event_chat_attachments(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $author = User::factory()->staff()->create();
        $path = 'event-communications/'.$event->uuid.'/brief.pdf';
        Storage::disk('local')->put($path, 'private briefing');
        $message = EventMessage::query()->create([
            'scheduling_event_id' => $event->id,
            'author_user_id' => $author->id,
            'message_type' => 'discussion',
            'body' => 'Please read this.',
            'attachment_path' => $path,
            'attachment_name' => 'Event brief.pdf',
            'attachment_mime' => 'application/pdf',
        ]);

        $downloadUrl = '/api/v1/chats/'.$event->uuid.'/messages/'.$message->uuid.'/attachment';
        $this->getJson('/api/v1/chats/'.$event->uuid.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.attachment.name', 'Event brief.pdf')
            ->assertJsonPath('data.0.attachment.mime_type', 'application/pdf')
            ->assertJsonPath('data.0.attachment.download_url', url($downloadUrl));

        $download = $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-disposition', 'attachment; filename="Event brief.pdf"');
        $this->assertSame('private briefing', $download->streamedContent());

        $this->authenticatedCrew();
        $this->get($downloadUrl)->assertNotFound();
    }

    public function test_assigned_crew_can_upload_a_private_event_chat_attachment(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/v1/chats/'.$event->uuid.'/messages', [
                'body' => 'Attached briefing.',
                'attachment' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.attachment.name', 'brief.pdf')
            ->assertJsonPath('data.attachment.mime_type', 'application/pdf')
            ->assertJsonPath('data.attachment.size', fn ($size): bool => is_int($size) && $size > 0);

        $message = EventMessage::query()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->get($response->json('data.attachment.download_url'))->assertOk();
    }

    public function test_multipart_idempotency_rejects_a_different_attachment_for_the_same_key(): void
    {
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local']);
        [, $profile] = $this->authenticatedCrew();
        $event = $this->assignedEvent($profile);
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)->post('/api/v1/chats/'.$event->uuid.'/messages', [
            'attachment' => UploadedFile::fake()->createWithContent('first.txt', 'first'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->post('/api/v1/chats/'.$event->uuid.'/messages', [
            'attachment' => UploadedFile::fake()->createWithContent('second.txt', 'second'),
        ], ['Accept' => 'application/json'])->assertConflict();

        $this->assertSame(1, $event->messages()->count());
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
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read.')
            ->assertJsonPath('data.id', $notification->uuid)
            ->assertJsonPath('data.read', true)
            ->assertJsonPath('data.allowed_actions', []);
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
