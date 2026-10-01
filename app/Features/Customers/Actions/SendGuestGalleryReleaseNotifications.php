<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Notifications\GuestGalleryReleased;
use App\Features\Customers\Services\GuestGalleryCatalogue;
use App\Features\Customers\Services\GuestStudioSubscriptions;
use App\Features\Scheduling\Models\SchedulingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendGuestGalleryReleaseNotifications
{
    public function __construct(private readonly GuestGalleryCatalogue $catalogue, private readonly GuestStudioSubscriptions $subscriptions) {}

    public function execute(?SchedulingEvent $onlyEvent = null): int
    {
        $this->subscriptions->prepareReleasedEvents($onlyEvent);
        $sent = 0;
        GuestGalleryRegistration::query()->whereNull('notified_at')->whereNull('unsubscribed_at')
            ->whereDoesntHave('event.concertBookingItem.concert.mediaCollections.concertPhotoReleaseGroups', fn ($groups) => $groups->whereNotNull('released_at'))
            ->when($onlyEvent, fn ($query) => $query->where('scheduling_event_id', $onlyEvent->id))
            ->where(fn ($query) => $query->whereNull('guest_studio_subscription_id')
                ->orWhereHas('studioSubscription', fn ($query) => $query->whereNull('unsubscribed_at')))
            ->with(['event.concertBookingItem.concert.bookingItem', 'user'])
            ->chunkById(250, function ($registrations) use (&$sent): void {
                foreach ($registrations as $registration) {
                    if (! $this->catalogue->eventIsReleased($registration->event)) {
                        continue;
                    }
                    DB::transaction(function () use ($registration, &$sent): void {
                        $locked = GuestGalleryRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
                        if ($locked->notified_at || $locked->unsubscribed_at) {
                            return;
                        }
                        $notification = (new GuestGalleryReleased($locked->load('event')))->afterCommit();
                        $locked->user
                            ? $locked->user->notify($notification)
                            : Notification::route('mail', $locked->email)->notify($notification);
                        $locked->update(['notified_at' => now()]);
                        $sent++;
                    });
                }

            });

        return $sent;
    }
}
