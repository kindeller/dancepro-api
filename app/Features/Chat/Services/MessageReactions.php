<?php

namespace App\Features\Chat\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MessageReactions
{
    public function update(User $user, string $chatId, string $messageId, ?string $reaction, CrewMobileChat $chats): array
    {
        [, $chat] = $chats->resolve($user, $chatId);
        $message = $chat->messages()->where('uuid', $messageId)->firstOrFail();
        abort_if($message->author_user_id === $user->id, 403, 'You cannot react to your own message.');
        if ($reaction === null) {
            $message->reactions()->where('user_id', $user->id)->delete();
        } else {
            $message->reactions()->updateOrCreate(['user_id' => $user->id], ['reaction' => $reaction]);
        }

        return self::summary($message, $user->id);
    }

    public static function summary(Model $message, int $userId): array
    {
        return $message->reactions->groupBy('reaction')->map(fn ($items, $reaction) => [
            'reaction' => $reaction, 'count' => $items->count(), 'selected' => $items->contains('user_id', $userId),
        ])->values()->all();
    }
}
