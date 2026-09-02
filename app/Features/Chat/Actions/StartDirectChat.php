<?php

namespace App\Features\Chat\Actions;

use App\Features\Chat\Models\DirectChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartDirectChat
{
    public function execute(User $sender, User $recipient): DirectChatConversation
    {
        abort_if($sender->is($recipient) || ! $recipient->is_active || $recipient->crewProfile === null, 422);

        return DB::transaction(function () use ($sender, $recipient): DirectChatConversation {
            $conversation = DirectChatConversation::query()->firstOrCreate([
                'participant_key' => DirectChatConversation::participantKey($sender->id, $recipient->id),
            ]);
            $conversation->participants()->syncWithoutDetaching([$sender->id, $recipient->id]);

            return $conversation;
        });
    }
}
