<?php

namespace App\Features\Messaging\Services;

use App\Features\Orders\Models\Order;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MessageRecipients
{
    public function users(string $channel, User $actor): Builder
    {
        return User::query()->where('is_active', true)->whereNull('anonymized_at')->whereNull('merged_into_user_id')
            ->when($channel === 'crew', fn ($q) => $q->whereHas('crewProfile')->whereKeyNot($actor->id))
            ->when($channel === 'customer', fn ($q) => $q->where(fn ($q) => $q->whereIn('type', ['customer', 'crew', 'staff'])->orWhere('is_admin', true)));
    }

    public function search(string $channel, string $term, User $actor): Collection
    {
        if (trim($term) === '') {
            return collect();
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
        if ($channel === 'studio') {
            return Studio::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_email', 'like', $like)
                ->orWhere('uuid', $term)->when(ctype_digit($term), fn ($q) => $q->orWhere('id', $term))
                ->orWhereHas('contacts', fn ($q) => $q->where('name', 'like', $like)->orWhere('emails', 'like', $like)))
                ->orderBy('name')->limit(25)->get()->map(fn ($studio) => ['reference' => $studio->uuid, 'name' => $studio->name, 'detail' => $studio->contact_email]);
        }
        $orderId = Order::idFromDisplayNumber($term);

        return $this->users($channel, $actor)->where(function ($q) use ($term, $like, $channel, $orderId): void {
            $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('uuid', $term)
                ->when($channel === 'crew' && ctype_digit($term), fn ($q) => $q->orWhereHas('crewProfile', fn ($p) => $p->where('id', $term)));
            if ($channel === 'customer') {
                $q->orWhereIn('id', Order::query()->select('user_id')->where(fn ($q) => $q->where('uuid', $term)->when($orderId, fn ($q) => $q->orWhere('id', $orderId))));
            } else {
                $q->orWhereHas('crewProfile', fn ($q) => $q->where('preferred_name', 'like', $like)->orWhere('uuid', $term));
            }
        })->orderBy('name')->limit(25)->get()->map(fn ($user) => ['reference' => $user->uuid, 'name' => $user->name, 'detail' => $user->email]);
    }
}
