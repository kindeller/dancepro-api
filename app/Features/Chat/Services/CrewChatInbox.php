<?php

namespace App\Features\Chat\Services;

use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Operations\Models\EventMessageRead;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrewChatInbox
{
    public function conversations(User $user, string $filter): Collection
    {
        $eventChats = $this->accessibleEventQuery($user)
            ->with(['messages' => fn ($query) => $query->with('author')->latest(), 'shifts'])
            ->get()
            ->map(function (SchedulingEvent $event) use ($user): array {
                $lastMessage = $event->messages->first();
                $isUpcoming = $event->shifts->pluck('shift_date')->filter()
                    ->contains(fn ($date): bool => $date->between(today(), today()->addDays(7)));
                $unreadCount = $event->messages()
                    ->where('author_user_id', '!=', $user->id)
                    ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->id))
                    ->count();

                return [
                    'kind' => 'event',
                    'model' => $event,
                    'title' => $event->name,
                    'subtitle' => $lastMessage?->body ?: 'Event chat is ready',
                    'activity_at' => $lastMessage?->created_at ?? $event->shifts->min('shift_date') ?? $event->event_date,
                    'unread_count' => $unreadCount,
                    'is_upcoming' => $isUpcoming,
                    'date' => $event->shifts->min('shift_date') ?? $event->event_date,
                ];
            });

        $directChats = DirectChatConversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->where('users.id', $user->id))
            ->with(['participants.crewProfile', 'messages' => fn ($query) => $query->with('author')->latest()])
            ->get()
            ->map(function (DirectChatConversation $conversation) use ($user): array {
                $other = $conversation->participants->firstWhere('id', '!=', $user->id);
                $lastMessage = $conversation->messages->first();
                $lastReadAt = $conversation->participants->firstWhere('id', $user->id)?->pivot?->last_read_at;
                $unreadCount = $conversation->messages()
                    ->where('author_user_id', '!=', $user->id)
                    ->when($lastReadAt, fn (Builder $query) => $query->where('created_at', '>', $lastReadAt))
                    ->count();

                return [
                    'kind' => 'direct',
                    'model' => $conversation,
                    'title' => $other?->crewProfile?->preferred_name ?: $other?->name ?: 'Crew member',
                    'subtitle' => $lastMessage?->body ?: 'Start the conversation',
                    'activity_at' => $lastMessage?->created_at ?? $conversation->created_at,
                    'unread_count' => $unreadCount,
                    'is_upcoming' => false,
                    'date' => null,
                ];
            });

        return $eventChats->concat($directChats)
            ->filter(fn (array $chat): bool => match ($filter) {
                'unread' => $chat['unread_count'] > 0,
                'upcoming' => $chat['kind'] === 'event' && $chat['is_upcoming'],
                'events' => $chat['kind'] === 'event',
                'direct' => $chat['kind'] === 'direct',
                default => true,
            })
            ->sortByDesc('activity_at')
            ->values();
    }

    public function accessibleEvent(User $user, SchedulingEvent $event): SchedulingEvent
    {
        return $this->accessibleEventQuery($user)->whereKey($event->id)->firstOrFail();
    }

    public function directConversation(User $user, DirectChatConversation $conversation): DirectChatConversation
    {
        abort_unless($conversation->participants()->whereKey($user->id)->exists(), 403);

        return $conversation;
    }

    public function markEventRead(User $user, SchedulingEvent $event): void
    {
        $event->messages()->where('author_user_id', '!=', $user->id)->pluck('id')->each(
            fn (int $messageId) => EventMessageRead::query()->updateOrCreate(
                ['event_message_id' => $messageId, 'user_id' => $user->id],
                ['read_at' => now()],
            ),
        );
    }

    public function markDirectRead(User $user, DirectChatConversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    public function availableCrew(User $user): Collection
    {
        return CrewProfile::query()->where('user_id', '!=', $user->id)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->with('user')->orderBy('preferred_name')->get();
    }

    private function accessibleEventQuery(User $user): Builder
    {
        $profileId = CrewProfile::query()->where('user_id', $user->id)->value('id');
        abort_unless($profileId, 403);

        return SchedulingEvent::query()
            ->whereHas('shifts.assignments', fn (Builder $query) => $query
                ->where('crew_profile_id', $profileId)
                ->where('status', 'published'))
            ->where(function (Builder $query): void {
                $query->whereHas('messages')
                    ->orWhereHas('shifts', fn (Builder $shiftQuery) => $shiftQuery
                        ->whereBetween('shift_date', [today(), today()->addDays(7)]));
            });
    }
}
