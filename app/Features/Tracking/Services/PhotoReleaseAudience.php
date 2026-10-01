<?php

namespace App\Features\Tracking\Services;

use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Customers\Models\GuestGalleryRegistration;
use App\Features\Customers\Models\GuestStudioSubscription;
use App\Features\Families\Services\FamilyGalleryAccess;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Models\User;
use Illuminate\Support\Collection;

class PhotoReleaseAudience
{
    public function customers(ConcertPhotoReleaseGroup $group): Collection
    {
        return app(FamilyGalleryAccess::class)->studioViewers($group->studio_id)->get()
            ->filter(fn ($user) => $user->canAccessCustomer())->unique(fn ($user) => mb_strtolower($user->email));
    }

    /** Deduplicate gallery updates, while preserving the separate Studio Hub message. */
    public function all(ConcertPhotoReleaseGroup $group): Collection
    {
        $studio = StudioAccountAccess::where('studio_id', $group->studio_id)->where('status', 'active')->whereNull('revoked_at')
            ->whereHas('user', fn ($user) => $user->where('is_active', true)->whereNotNull('email_verified_at')->whereNull('anonymized_at')->whereNull('merged_into_user_id'))
            ->with('user')->get()->map(fn ($access) => $this->recipient($access->user->email, $access->user->name, 'studio', $access->user->id, $access->user->emailGreetingName()));
        $customers = $this->customers($group)->map(fn ($user) => $this->recipient($user->email, $user->name, 'customer', $user->id, $user->emailGreetingName()));
        $eventIds = $group->collections()->with('concert.bookingItem')->get()->pluck('concert.bookingItem.scheduling_event_id')->filter();
        $subscriptions = GuestStudioSubscription::where('studio_id', $group->studio_id)->get();
        $registrations = GuestGalleryRegistration::whereIn('scheduling_event_id', $eventIds)->get();
        $blocked = $subscriptions->whereNotNull('unsubscribed_at')->pluck('email_hash')
            ->merge($registrations->whereNotNull('unsubscribed_at')->pluck('email_hash'));
        $guests = $subscriptions->whereNull('unsubscribed_at')->map(fn ($entry) => $this->recipient($entry->email, 'DancePro family', 'guest'))
            ->concat($registrations->whereNull('unsubscribed_at')->map(fn ($entry) => $this->recipient($entry->email, 'DancePro family', 'guest')))
            ->concat(GuestGalleryAccess::where('gallery_type', 'concert')->whereHas('concert', fn ($concert) => $concert->where('studio_id', $group->studio_id))
                ->get()->map(fn ($entry) => $this->recipient($entry->email, 'DancePro family', 'guest')))
            ->reject(fn ($entry) => $blocked->contains(hash('sha256', $entry->email)));
        // An old guest entry must not restore correspondence to a deactivated account.
        $inactiveEmails = User::whereIn('email', $guests->pluck('email'))->where(fn ($user) => $user->where('is_active', false)->orWhereNotNull('anonymized_at')->orWhereNotNull('merged_into_user_id'))->pluck('email')->map(fn ($email) => mb_strtolower($email));

        $gallery = $customers->concat($guests->reject(fn ($entry) => $inactiveEmails->contains($entry->email)))->unique('email');

        return $gallery->concat($studio->unique('email'))
            ->filter(fn ($entry) => filter_var($entry->email, FILTER_VALIDATE_EMAIL))->values();
    }

    public function current(ConcertPhotoReleaseGroup $group, ConcertTrackerEmailDelivery $delivery): bool
    {
        $email = mb_strtolower($delivery->recipient_email);
        $audience = data_get($delivery->workflow_context, 'photo_release_audience', 'customer');
        $userId = data_get($delivery->workflow_context, 'recipient_user_id');
        if ($audience === 'customer') {
            return app(FamilyGalleryAccess::class)->studioViewers($group->studio_id)
                ->when($userId, fn ($query) => $query->whereKey($userId))->whereRaw('lower(email) = ?', [$email])->exists();
        }
        if ($audience === 'studio') {
            if (data_get($delivery->workflow_context, 'studio_join_version') !== app(PublicityEventAccess::class)->studioVersion($group->studio)) {
                return false;
            }

            return StudioAccountAccess::where('studio_id', $group->studio_id)->where('status', 'active')->whereNull('revoked_at')
                ->where('user_id', $userId)->whereHas('user', fn ($user) => $user->where('is_active', true)->whereNotNull('email_verified_at')
                ->whereNull('anonymized_at')->whereNull('merged_into_user_id')->whereRaw('lower(email) = ?', [$email]))->exists();
        }
        if ($audience !== 'guest' || User::whereRaw('lower(email) = ?', [$email])->where(fn ($user) => $user->where('is_active', false)->orWhereNotNull('anonymized_at')->orWhereNotNull('merged_into_user_id'))->exists()) {
            return false;
        }
        $hash = hash('sha256', $email);
        $subscriptions = GuestStudioSubscription::where('studio_id', $group->studio_id)->where('email_hash', $hash);
        $eventIds = $group->collections()->with('concert.bookingItem')->get()->pluck('concert.bookingItem.scheduling_event_id')->filter();
        $registrations = GuestGalleryRegistration::whereIn('scheduling_event_id', $eventIds)->where('email_hash', $hash);
        if ((clone $subscriptions)->whereNotNull('unsubscribed_at')->exists() || (clone $registrations)->whereNotNull('unsubscribed_at')->exists()) {
            return false;
        }

        return $subscriptions->whereNull('unsubscribed_at')->exists() || $registrations->whereNull('unsubscribed_at')->exists()
            || GuestGalleryAccess::where('gallery_type', 'concert')->where('email_hash', $hash)->whereHas('concert', fn ($concert) => $concert->where('studio_id', $group->studio_id))->exists();
    }

    private function recipient(string $email, string $name, string $audience, ?int $userId = null, string $firstName = ''): object
    {
        return (object) ['email' => mb_strtolower(trim($email)), 'name' => $name, 'audience' => $audience, 'user_id' => $userId, 'first_name' => $firstName];
    }
}
