<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\EventMessage;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostEventMessage
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function execute(SchedulingEvent $event, User $author, string $type, ?string $body, ?UploadedFile $attachment): EventMessage
    {
        return DB::transaction(function () use ($event, $author, $type, $body, $attachment): EventMessage {
            $path = $attachment ? $this->files->store($attachment, 'event-communications/'.$event->uuid) : null;
            $message = $event->messages()->create([
                'author_user_id' => $author->id,
                'message_type' => $type,
                'body' => filled($body) ? trim($body) : null,
                'attachment_path' => $path,
                'attachment_name' => $attachment ? $this->safeFilename($attachment->getClientOriginalName()) : null,
                'attachment_mime' => $attachment?->getMimeType(),
                'attachment_size' => $attachment?->getSize(),
            ]);

            $userIds = $event->shifts()->with('assignments.crewProfile')->get()
                ->flatMap->assignments->pluck('crewProfile.user_id')->filter()
                ->unique()->reject(fn (int $userId): bool => $userId === $author->id);
            foreach ($userIds as $userId) {
                CrewNotification::query()->create([
                    'user_id' => $userId,
                    'type' => $type === 'announcement' ? 'event_announcement' : 'event_message',
                    'title' => $type === 'announcement' ? 'Important update: '.$event->name : 'New event message: '.$event->name,
                    'message' => $message->body ?: ($type === 'announcement'
                        ? 'A new event attachment needs your acknowledgement.'
                        : 'A new attachment was shared in the event chat.'),
                    'destination_type' => 'chat',
                    'destination_id' => $event->uuid,
                ]);
            }

            return $message;
        });
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?: 'attachment';

        return mb_substr($filename, 0, 255);
    }
}
