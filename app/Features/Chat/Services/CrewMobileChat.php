<?php

namespace App\Features\Chat\Services;

use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\EventMessageRead;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrewMobileChat
{
    public function __construct(private readonly CrewChatInbox $inbox) {}

    public function conversations(User $user, string $filter, int $limit, ?string $cursor): array
    {
        $items = $this->inbox->conversations($user, $filter);
        $offset = $this->decodeCursor($cursor);
        $page = $items->slice($offset, $limit)->map(fn (array $chat): array => $this->summary($chat))->values();
        $nextOffset = $offset + $page->count();
        $hasMore = $items->count() > $nextOffset;

        return [
            'items' => $page,
            'next_cursor' => $hasMore ? base64_encode((string) $nextOffset) : null,
            'has_more' => $hasMore,
        ];
    }

    public function messages(User $user, string $uuid, int $limit): array
    {
        [$kind, $chat] = $this->resolve($user, $uuid);
        $page = $chat->messages()->with('author.crewProfile')->latest('id')->cursorPaginate($limit);

        return ['kind' => $kind, 'chat' => $chat, 'page' => $page];
    }

    public function resolve(User $user, string $uuid): array
    {
        $event = SchedulingEvent::query()->where('uuid', $uuid)->first();
        if ($event !== null) {
            return ['event', $this->inbox->accessibleEvent($user, $event)];
        }

        $direct = DirectChatConversation::query()->where('uuid', $uuid)
            ->whereHas('participants', fn (Builder $query) => $query->where('users.id', $user->id))
            ->firstOrFail();

        return ['direct', $direct];
    }

    public function markRead(User $user, string $uuid, string $messageUuid): void
    {
        [$kind, $chat] = $this->resolve($user, $uuid);

        if ($kind === 'event') {
            $message = $chat->messages()->where('uuid', $messageUuid)->firstOrFail();
            $chat->messages()->where('id', '<=', $message->id)->where('author_user_id', '!=', $user->id)
                ->pluck('id')->each(fn (int $id) => EventMessageRead::query()->updateOrCreate(
                    ['event_message_id' => $id, 'user_id' => $user->id],
                    ['read_at' => now()],
                ));

            return;
        }

        $message = $chat->messages()->where('uuid', $messageUuid)->firstOrFail();
        $chat->participants()->updateExistingPivot($user->id, ['last_read_at' => $message->created_at]);
    }

    public function attachment(User $user, string $chatUuid, string $messageUuid): EventMessage
    {
        [$kind, $chat] = $this->resolve($user, $chatUuid);
        abort_unless($kind === 'event', 404);

        return $chat->messages()
            ->where('uuid', $messageUuid)
            ->whereNotNull('attachment_path')
            ->firstOrFail();
    }

    public function messageResource(Model $message, string $chatUuid): array
    {
        $profile = $message->author?->crewProfile;

        return [
            'id' => $message->uuid,
            'author' => [
                'id' => $profile?->uuid,
                'name' => $profile?->preferred_name ?: $message->author?->name ?: 'Staff',
                'phone' => $profile?->phone,
                'profile_photo_url' => null,
            ],
            'body' => (string) $message->body,
            'attachment' => $message instanceof EventMessage && filled($message->attachment_path) ? [
                'name' => $message->attachment_name ?: basename($message->attachment_path),
                'mime_type' => $message->attachment_mime,
                'download_url' => route('api.v1.chats.attachments.show', [
                    'chatId' => $chatUuid,
                    'message' => $message->uuid,
                ]),
            ] : null,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    private function summary(array $chat): array
    {
        return [
            'id' => $chat['model']->uuid,
            'kind' => $chat['kind'],
            'title' => $chat['title'],
            'latest_message_preview' => $chat['subtitle'],
            'unread_count' => $chat['unread_count'],
            'activity_at' => $chat['activity_at']?->toIso8601String(),
        ];
    }

    private function decodeCursor(?string $cursor): int
    {
        if (! filled($cursor)) {
            return 0;
        }

        $decoded = base64_decode($cursor, true);
        abort_unless(is_string($decoded) && ctype_digit($decoded), 422, 'The cursor is invalid.');

        return (int) $decoded;
    }
}
