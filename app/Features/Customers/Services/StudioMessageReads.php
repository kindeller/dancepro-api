<?php

namespace App\Features\Customers\Services;

use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;

class StudioMessageReads
{
    public function __construct(private readonly StudioCommunicationHistory $access) {}

    public function read(User $user, Studio $studio, string $source, string $uuid): array
    {
        abort_unless($this->access->canView($user, $studio), 403);
        $message = match ($source) {
            'message' => StudioConversationMessage::where('studio_id', $studio->id)->where('direction', 'from_dancepro')->where('uuid', $uuid)->firstOrFail(),
            'email' => ConcertTrackerEmailDelivery::whereNull('photo_release_group_id')->whereHas('event', fn ($query) => $query->where('studio_id', $studio->id))
                ->where('status', 'sent')->where('uuid', $uuid)->firstOrFail(),
            default => abort(404),
        };
        $message->newQuery()->whereKey($message->id)->whereNull('studio_read_at')->update(['studio_read_at' => now()]);

        return ['studio' => $this->unreadCount($studio)];
    }

    public function unreadCount(Studio $studio): int
    {
        return StudioConversationMessage::where('studio_id', $studio->id)->where('direction', 'from_dancepro')->whereNull('studio_read_at')->count()
            + ConcertTrackerEmailDelivery::whereNull('photo_release_group_id')->whereHas('event', fn ($query) => $query->where('studio_id', $studio->id))
                ->where('status', 'sent')->whereNull('studio_read_at')->count();
    }
}
