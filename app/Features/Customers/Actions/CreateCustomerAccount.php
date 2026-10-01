<?php

namespace App\Features\Customers\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCustomerAccount
{
    public function execute(array $input, string $source = 'web'): User
    {
        try {
            return DB::transaction(function () use ($input, $source): User {
                $user = User::create([
                    'name' => $input['name'], 'email' => strtolower(trim($input['email'])),
                    ...array_intersect_key($input, array_flip(['first_name', 'last_name'])),
                    'password' => $input['password'], 'type' => 'customer', 'is_admin' => false,
                    'is_active' => true,
                ]);
                $user->customerProfile()->create(['registration_source' => $source, 'terms_accepted_at' => now(),
                    'privacy_accepted_at' => now(), 'policy_version' => config('customer.policy_version')]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Unable to create an account with this email. Try signing in or resetting your password.']);
        }
    }
}
