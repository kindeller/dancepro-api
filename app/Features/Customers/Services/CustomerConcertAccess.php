<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CustomerConcertAccess
{
    public function allows(User $user, Concert $concert): bool
    {
        if (! $user->canAccessCustomer() || ! $concert->isPubliclyAvailable()) {
            return false;
        }

        return $this->grants($user)->where('concert_id', $concert->id)->get()
            ->contains(fn ($grant) => $this->matches($grant, $concert));
    }

    public function allowsStreaming(User $user, Concert $concert): bool
    {
        return $this->allows($user, $concert) && $this->grants($user)->where('concert_id', $concert->id)->with('codeGroup')->get()
            ->contains(fn (ConcertAccessGrant $grant) => $this->matches($grant, $concert)
                && ($grant->concert_code_group_id === null || $grant->codeGroup?->streamEndsAt()?->isFuture()));
    }

    public function grants(User $user): Builder
    {
        return ConcertAccessGrant::where('user_id', $user->id)
            ->whereIn('status', ['active', 'claimed'])->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function matches(ConcertAccessGrant $grant, Concert $concert): bool
    {
        if ($grant->source->value !== 'password') {
            return $grant->concert_id === $concert->id;
        }

        if ($grant->concert_code_group_id !== null) {
            $group = $grant->codeGroup;

            return $group && $group->released_at !== null && $group->downloadEndsAt()?->isFuture()
                && $concert->concert_code_group_id === $group->id;
        }
        $fingerprint = $grant->metadata['code_fingerprint'] ?? null;

        return is_string($fingerprint) && $concert->access_password_hash !== null
            && hash_equals($fingerprint, hash('sha256', $concert->access_password_hash));
    }
}
