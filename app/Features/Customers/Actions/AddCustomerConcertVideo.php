<?php

namespace App\Features\Customers\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Concerts\Support\ConcertAccessGrantStatus;
use App\Features\Customers\Models\CustomerStudio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddCustomerConcertVideo
{
    public function execute(User $user, string $code, int $year): int
    {
        abort_unless($user->canAccessCustomer(), 403);
        $studios = CustomerStudio::where('user_id', $user->id)
            ->whereNotNull('access_verified_at')->whereHas('studio', fn ($query) => $query->where('status', 'active'))->pluck('studio_id');
        if ($studios->isEmpty()) {
            throw ValidationException::withMessages(['concert_code' => 'Add your studio in My Profile first.']);
        }
        $groups = ConcertCodeGroup::whereIn('studio_id', $studios)->where('year', $year)->whereNotNull('released_at')->get()
            ->filter(fn (ConcertCodeGroup $group) => $group->downloadEndsAt()?->isFuture() && $group->matches($code));
        if ($groups->isNotEmpty()) {
            return $this->addGroups($user, $groups, $code);
        }
        // Existing concerts each own their password. One supplied code can match multiple concerts.
        $ids = Concert::whereIn('studio_id', $studios)->whereYear('event_date', $year)->whereNotNull('access_password_hash')->where('is_enabled', true)->where('status', 'published')
            ->with('bookingItem')->get()->filter(fn ($concert) => $concert->isPubliclyAvailable() && $concert->passwordMatches($code))->pluck('id');

        return DB::transaction(function () use ($user, $ids, $code, $year, $studios): int {
            // Serialize additions by this account; never create a second grant to bypass an old expiry/revocation.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canAccessCustomer(), 403);
            $count = 0;
            foreach (Concert::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get() as $concert) {
                if (! $studios->contains($concert->studio_id) || $concert->event_date?->year !== $year
                    || ! $concert->isPubliclyAvailable() || ! $concert->passwordMatches($code)) {
                    continue;
                }
                $grant = ConcertAccessGrant::withTrashed()->where('user_id', $user->id)->where('concert_id', $concert->id)->where('source', 'password')->whereNull('concert_code_group_id')->first();
                if ($grant && ($grant->trashed() || $grant->revoked_at || ! in_array($grant->status, [ConcertAccessGrantStatus::Active, ConcertAccessGrantStatus::Claimed], true)
                    || ($grant->expires_at && $grant->expires_at->isPast()))) {
                    continue;
                }
                if (! $grant) {
                    $grant = new ConcertAccessGrant(['user_id' => $user->id, 'concert_id' => $concert->id,
                        'source' => 'password', 'status' => 'active', 'claimed_at' => now(), 'expires_at' => $concert->available_until]);
                }
                $grant->metadata = array_merge($grant->metadata ?? [], ['code_fingerprint' => hash('sha256', $concert->access_password_hash)]);
                $grant->save();
                $count++;
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['concert_code' => 'No available concert videos could be added with this code. Check the code supplied by your studio or contact DancePro.']);
            }

            return $count;
        });
    }

    private function addGroups(User $user, $groups, string $code): int
    {
        return DB::transaction(function () use ($user, $groups, $code): int {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            foreach (ConcertCodeGroup::whereIn('id', $groups->pluck('id'))->orderBy('id')->lockForUpdate()->get() as $group) {
                if (! $group->released_at || ! $group->downloadEndsAt()?->isFuture() || ! $group->matches($code)) {
                    continue;
                }
                foreach ($group->concerts()->with('bookingItem')->orderBy('id')->get() as $concert) {
                    if (! $concert->isPubliclyAvailable()) {
                        continue;
                    }
                    $grant = ConcertAccessGrant::withTrashed()->where('user_id', $user->id)->where('concert_id', $concert->id)
                        ->where('concert_code_group_id', $group->id)->first();
                    if ($grant && ($grant->trashed() || $grant->revoked_at || ! in_array($grant->status, [ConcertAccessGrantStatus::Active, ConcertAccessGrantStatus::Claimed], true)
                        || ($grant->expires_at && $grant->expires_at->isPast()))) {
                        continue;
                    }
                    if (! $grant) {
                        $grant = new ConcertAccessGrant(['user_id' => $user->id, 'concert_id' => $concert->id,
                            'source' => 'password', 'status' => 'active', 'claimed_at' => now(), 'expires_at' => $group->downloadEndsAt()]);
                        $grant->concert_code_group_id = $group->id;
                    }
                    $grant->save();
                    $count++;
                }
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['concert_code' => 'No available concert videos could be added with this code. Check the code supplied by your studio or contact DancePro.']);
            }

            return $count;
        });
    }
}
