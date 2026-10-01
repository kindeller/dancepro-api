<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestStudioSubscriptions
{
    public function __construct(private readonly GuestGalleryCatalogue $catalogue) {}

    public function register(Studio $studio, string $email, ?User $user): GuestStudioSubscription
    {
        if ($this->catalogue->concerts($studio)->isEmpty()) {
            throw ValidationException::withMessages(['email' => 'Registration is not available for this studio.']);
        }
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($studio, $email, $user): GuestStudioSubscription {
            $subscription = GuestStudioSubscription::firstOrCreate(
                ['studio_id' => $studio->id, 'email_hash' => hash('sha256', $email)],
                ['email' => $email, 'user_id' => $user?->id, 'consent_at' => now()],
            );
            $subscription = GuestStudioSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($subscription->unsubscribed_at) {
                $subscription->fill(['consent_at' => now(), 'unsubscribed_at' => null]);
            }
            if ($user) {
                $subscription->user_id = $user->id;
            }
            $subscription->save();

            return $subscription;
        });
    }

    /** Reuse the per-event delivery record so repeat scheduler runs cannot send duplicates. */
    public function prepareReleasedEvents(?SchedulingEvent $onlyEvent = null): void
    {
        GuestStudioSubscription::whereNull('unsubscribed_at')->whereHas('studio')
            ->when($onlyEvent, fn ($query) => $query->where('studio_id', $onlyEvent->studio_id))
            ->chunkById(100, function ($subscriptions) use ($onlyEvent): void {
                foreach ($subscriptions as $subscription) {
                    $concerts = Concert::where('studio_id', $subscription->studio_id)
                        ->whereDoesntHave('mediaCollections.concertPhotoReleaseGroups', fn ($groups) => $groups->whereNotNull('released_at'))
                        ->whereHas('bookingItem.schedulingEvent', fn ($query) => $query->whereNull('cancelled_at')
                            ->when($onlyEvent, fn ($query) => $query->whereKey($onlyEvent->id)))
                        ->whereHas('mediaCollections', fn ($query) => $query->where('media_type', 'photo')
                            ->where('status', 'published')->whereIn('visibility', ['customer', 'public'])
                            ->where('published_at', '>=', $subscription->consent_at))
                        ->with('bookingItem.schedulingEvent', 'mediaCollections')->get();
                    foreach ($concerts as $concert) {
                        $firstRelease = $concert->mediaCollections
                            ->filter(fn ($collection) => $collection->media_type->value === 'photo' && $collection->status->value === 'published'
                                && in_array($collection->visibility->value, ['customer', 'public']))
                            ->pluck('published_at')->filter()->min();
                        if (! $firstRelease || $firstRelease->lt($subscription->consent_at)
                            || ! $this->catalogue->concertIsReleased($concert)) {
                            continue;
                        }
                        GuestGalleryRegistration::firstOrCreate([
                            'scheduling_event_id' => $concert->bookingItem->scheduling_event_id,
                            'email_hash' => $subscription->email_hash,
                        ], [
                            'email' => $subscription->email, 'user_id' => $subscription->user_id,
                            'consent_at' => $subscription->consent_at,
                            'guest_studio_subscription_id' => $subscription->id,
                        ]);
                    }
                }
            });
    }

    public function unsubscribe(GuestStudioSubscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription->update(['unsubscribed_at' => now()]);
            GuestGalleryRegistration::where('guest_studio_subscription_id', $subscription->id)
                ->update(['unsubscribed_at' => now()]);
        });
    }
}
