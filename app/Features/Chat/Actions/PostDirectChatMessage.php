<?php

namespace App\Features\Chat\Actions;

use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Chat\Models\DirectChatMessage;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\CrewNotification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostDirectChatMessage
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    public function execute(DirectChatConversation $conversation, User $author, ?string $body, ?UploadedFile $attachment = null): DirectChatMessage
    {
        abort_unless($conversation->participants()->whereKey($author->id)->exists(), 403);

        $path = $attachment?->isValid()
            ? $this->files->store($attachment, 'operations/chat/direct/'.$conversation->uuid)
            : null;

        try {
            $message = DB::transaction(function () use ($conversation, $author, $body, $attachment, $path): DirectChatMessage {
                $message = $this->createMessage($conversation, $author, $body, $attachment, $path);
                $conversation->participants()->updateExistingPivot($author->id, ['last_read_at' => now()]);
                $conversation->touch();

                $preview = $this->notificationPreview($author, $attachment);
                $conversation->participants()->whereKeyNot($author->id)->pluck('users.id')->each(
                    fn (int $userId) => CrewNotification::query()->create([
                        'user_id' => $userId,
                        'type' => 'direct_message',
                        'title' => 'New crew message',
                        'message' => $preview,
                        'destination_type' => 'chat',
                        'destination_id' => $conversation->uuid,
                    ]),
                );

                return $message;
            });
        } catch (Throwable $exception) {
            if (filled($path)) {
                $this->files->disk()->delete($path);
            }

            throw $exception;
        }

        return $message;
    }

    protected function createMessage(DirectChatConversation $conversation, User $author, ?string $body, ?UploadedFile $attachment, ?string $path): DirectChatMessage
    {
        return $conversation->messages()->create([
            'author_user_id' => $author->id,
            'body' => filled($body) ? trim((string) $body) : null,
            'attachment_path' => $path,
            'attachment_name' => $attachment ? $this->safeOriginalName($attachment) : null,
            'attachment_mime' => $attachment?->getMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename($file->getClientOriginalName())) ?: 'attachment';

        return mb_substr($name, 0, 255);
    }

    private function notificationPreview(User $author, ?UploadedFile $attachment): string
    {
        if ($attachment === null) {
            return 'You have a new private message.';
        }

        $name = $author->crewProfile?->preferred_name ?: $author->name ?: 'A crew member';
        $kind = str_starts_with((string) $attachment->getMimeType(), 'image/') ? 'photo' : 'file';

        return $name.' sent a '.$kind;
    }
}
