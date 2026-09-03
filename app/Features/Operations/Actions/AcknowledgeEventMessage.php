<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Models\EventMessageRead;
use App\Models\User;

class AcknowledgeEventMessage
{
    public function execute(EventMessage $message, User $user): EventMessageRead
    {
        abort_unless($message->isAnnouncement(), 404);

        return EventMessageRead::query()->updateOrCreate(
            ['event_message_id' => $message->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }
}
