<?php

namespace App\Features\Families\Services;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FamilyAccess
{
    public function membership(User $user): ?CustomerFamilyMember
    {
        return CustomerFamilyMember::with('family.manager')->where('user_id', $user->id)->where('status', 'active')->first();
    }

    /** Direct family buyers only: borrowed entitlements are never re-shared. */
    public function purchaseOwnerIds(User $user): array
    {
        $member = $this->membership($user);
        if (! $member || ! $member->can_view_media || ! $member->family?->manager?->canAccessCustomer()) {
            return [$user->id];
        }

        return $member->family->members()->where('status', 'active')->whereNotNull('user_id')
            ->with('user')->get()->filter(fn ($member) => $member->user?->canAccessCustomer())
            ->pluck('user_id')->push($user->id)->unique()->values()->all();
    }

    public function canViewSharedOrder(User $user, Order $order): bool
    {
        return $user->canAccessCustomer() && $user->hasVerifiedEmail()
            && $order->user_id !== $user->id
            && in_array($order->user_id, $this->purchaseOwnerIds($user), true)
            && in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled], true)
            && $order->paid_at !== null;
    }

    public function requirePurchasing(User $user): void
    {
        $member = $this->membership($user);
        if ($member && ! $member->can_purchase) {
            throw ValidationException::withMessages(['payment' => 'Your family permissions allow favourites and a cart, but not purchases. Ask your account manager to enable Make purchases.']);
        }
    }

    public function dancers(User $user): Collection
    {
        $member = $this->membership($user);
        if (! $member) {
            return CustomerDancer::where('user_id', $user->id)->orderBy('name')->get();
        }
        $ids = $member->family->members()->where('is_dancer', true)->whereNotNull('customer_dancer_id')->pluck('customer_dancer_id');

        // Keep legacy personal dancer profiles available until imported by their manager.
        return CustomerDancer::where(fn ($query) => $query->whereIn('id', $ids)
            ->orWhere(fn ($personal) => $personal->where('user_id', $user->id)
                ->whereNotIn('id', CustomerFamilyMember::whereNotNull('customer_dancer_id')->select('customer_dancer_id'))))
            ->orderBy('name')->get();
    }
}
