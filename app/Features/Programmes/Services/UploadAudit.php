<?php

namespace App\Features\Programmes\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UploadAudit
{
    public function record(SchedulingEvent $event, string $roleUuid, User $actor, string $action, ?string $fileUuid = null, array $details = []): void
    {
        DB::table('programme_upload_audits')->insert([
            'event_uuid' => $event->uuid, 'role_uuid' => $roleUuid, 'actor_user_id' => $actor->id,
            'action' => $action, 'file_uuid' => $fileUuid, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
