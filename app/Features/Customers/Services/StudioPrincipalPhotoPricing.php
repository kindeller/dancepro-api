<?php

namespace App\Features\Customers\Services;

use App\Features\Studios\Models\Studio;
use App\Models\User;

class StudioPrincipalPhotoPricing
{
    public const PRICE = 100;

    public function eligible(User $user, Studio $studio): bool
    {
        $email = mb_strtolower(trim($user->email));
        if ($email === '') {
            return false;
        }
        if (mb_strtolower(trim((string) $studio->contact_email)) === $email) {
            return true;
        }
        $studio->loadMissing('contacts');

        return $studio->contacts->contains(function ($contact) use ($email): bool {
            $authorisedRole = preg_match('/\b(owner|principal|director)\b/i', (string) $contact->role) === 1;

            return $authorisedRole && in_array($email, array_map(
                fn (string $address): string => mb_strtolower(trim($address)),
                $contact->emailAddresses(),
            ), true);
        });
    }
}
