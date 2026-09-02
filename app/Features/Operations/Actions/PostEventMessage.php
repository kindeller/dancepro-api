<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\EventMessage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostEventMessage
{
    public function execute(SchedulingEvent $event, User $author, string $type, ?string $body, ?UploadedFile $attachment): EventMessage
    {
        return DB::transaction(function () use ($event, $author, $type, $body, $attachment): EventMessage {
            $path = $attachment?->store('event-communications/'.$event->uuid, 'public');
            $message = $event->messages()->create([
                'author_user_id' => $author->id,
                'message_type' => $type,
                'body' => filled($body) ? trim($body) : null,
                'attachment_path' => $path,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'attachment_mime' => $attachment?->getMimeType(),
            ]);

            if ($type === 'announcement') {
                $userIds = $event->shifts()->with('assignments.crewProfile')->get()
                    ->flatMap->assignments->pluck('crewProfile.user_id')->filter()->unique();
                foreach ($userIds as $userId) {
                    CrewNotification::query()->create([
                        'user_id' => $userId,
                        'type' => 'event_announcement',
                        'title' => 'Important update: '.$event->name,
                        'message' => $message->body ?: 'A new event attachment needs your acknowledgement.',
                    ]);
                }
            }

            return $message;
        });
    }
}
