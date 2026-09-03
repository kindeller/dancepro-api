<?php

namespace App\Features\Chat\Actions;

use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Chat\Models\DirectChatMessage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Models\User;

class PostDirectChatMessage
{
    public function execute(DirectChatConversation $conversation, User $author, string $body): DirectChatMessage
    {
        abort_unless($conversation->participants()->whereKey($author->id)->exists(), 403);

        $message = $conversation->messages()->create([
            'author_user_id' => $author->id,
            'body' => trim($body),
        ]);
        $conversation->participants()->updateExistingPivot($author->id, ['last_read_at' => now()]);
        $conversation->touch();
        $conversation->participants()->whereKeyNot($author->id)->pluck('users.id')->each(
            fn (int $userId) => CrewNotification::query()->create([
                'user_id' => $userId,
                'type' => 'direct_message',
                'title' => 'New crew message',
                'message' => 'You have a new private message.',
                'destination_type' => 'chat',
                'destination_id' => $conversation->uuid,
            ]),
        );

        return $message;
    }
}
