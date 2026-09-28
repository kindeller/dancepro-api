<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerEngagementEvent;
use App\Models\User;

class CustomerEngagementTracker
{
    public function record(User $user, string $kind, string $eventUuid, string $action, ?string $subjectUuid = null, array $metadata = []): void
    {
        CustomerEngagementEvent::create(['user_id' => $user->id, 'event_kind' => $kind, 'event_uuid' => $eventUuid,
            'action' => $action, 'subject_uuid' => $subjectUuid, 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
