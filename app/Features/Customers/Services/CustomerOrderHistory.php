<?php

namespace App\Features\Customers\Services;

use App\Features\Orders\Models\Order;
use App\Models\User;

class CustomerOrderHistory
{
    public function forUser(User $user, int $perPage = 20)
    {
        abort_unless($user->canAccessCustomer(), 403);

        return Order::where('user_id', $user->id)->whereIn('status', ['pending', 'paid', 'fulfilled'])
            ->with('items.mediaCollection.concert', 'items.mediaAsset.collection.concert', 'items.mediaEntitlements.assets')
            ->orderByDesc('placed_at')->orderByDesc('id')->paginate($perPage);
    }
}
