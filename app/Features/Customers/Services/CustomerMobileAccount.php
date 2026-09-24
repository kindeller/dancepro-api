<?php

namespace App\Features\Customers\Services;

use App\Models\User;

class CustomerMobileAccount
{
    public function forUser(User $user): array
    {
        $profile = $user->customerProfile;

        return [
            'name' => $user->name, 'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'policy_accepted' => $profile?->terms_accepted_at !== null && $profile?->privacy_accepted_at !== null
                && $profile?->policy_version === config('customer.policy_version'),
            'policy_version' => config('customer.policy_version'),
            'terms_url' => config('customer.terms_url'), 'privacy_url' => config('customer.privacy_url'),
        ];
    }
}
