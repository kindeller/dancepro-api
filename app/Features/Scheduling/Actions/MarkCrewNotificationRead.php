<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\CrewNotification;
use App\Models\User;

class MarkCrewNotificationRead
{
    public function execute(User $user, CrewNotification $notification): void
    {
        abort_unless($notification->user_id === $user->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }
}
