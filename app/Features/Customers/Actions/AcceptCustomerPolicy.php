<?php

namespace App\Features\Customers\Actions;

use App\Models\User;

class AcceptCustomerPolicy
{
    public function execute(User $user): void
    {
        abort_unless($user->canAccessCustomer() && $user->hasVerifiedEmail(), 403);
        $user->customerProfile()->updateOrCreate(['user_id' => $user->id], [
            'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
            'policy_version' => config('customer.policy_version'),
        ]);
        $user->unsetRelation('customerProfile');
    }
}
