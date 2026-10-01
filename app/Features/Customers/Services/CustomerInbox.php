<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class CustomerInbox
{
    public function counts(User $user): array
    {
        $notifications = CustomerInboxNotification::where('user_id', $user->id)->whereNull('read_at')->count();
        $conversations = CustomerSupportRequest::where('user_id', $user->id)->whereNotNull('latest_staff_message_at')
            ->where(fn ($q) => $q->whereNull('customer_read_at')->orWhereColumn('customer_read_at', '<', 'latest_staff_message_at'))->count();

        return ['notifications' => $notifications, 'conversations' => $conversations, 'total' => $notifications + $conversations];
    }

    public function page(User $user, string $tab): array
    {
        return ['messageTab' => $tab, 'inboxCounts' => $this->counts($user),
            'notifications' => $tab === 'notifications' ? CustomerInboxNotification::where('user_id', $user->id)->latest('occurred_at')->latest('id')->paginate(20)->withQueryString() : null,
            'conversations' => $tab === 'conversations' ? CustomerSupportRequest::where('user_id', $user->id)->with('accountEmail')->orderByDesc('last_activity_at')->latest('id')->paginate(20)->withQueryString() : null,
            'orders' => $tab === 'new' ? app(CustomerOrderHistory::class)->forUser($user, 100) : collect(),
            'events' => $tab === 'new' ? $this->events($user)->get() : collect(),
        ];
    }

    public function events(User $user)
    {
        return SchedulingEvent::whereNull('cancelled_at')->where(function ($query) use ($user): void {
            $query->whereIn('id', app(FamilyGalleryAccess::class)->competitions($user)->whereNotNull('access_verified_at')->select('scheduling_event_id'))
                ->orWhereIn('studio_id', app(FamilyGalleryAccess::class)->studios($user)->whereNotNull('access_verified_at')->select('studio_id'));
        })->orderByDesc('event_date');
    }

    public function read(CustomerInboxNotification $notification, User $user): void
    {
        abort_unless($notification->user_id === $user->id, 404);
        DB::transaction(function () use ($notification, $user): void {
            $notification->update(['read_at' => $notification->read_at ?? now()]);
            if (str_starts_with($notification->source_key, 'notification:')) {
                DatabaseNotification::where('notifiable_id', $user->id)->where('notifiable_type', $user->getMorphClass())
                    ->whereKey(substr($notification->source_key, strlen('notification:')))->update(['read_at' => $notification->read_at]);
            }
        });
    }
}
