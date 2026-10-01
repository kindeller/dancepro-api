<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Services\CustomerWelcome;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VerifyCustomerEmail
{
    public function __construct(private readonly CustomerWelcome $welcome) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($account->canAccessCustomer() && $account->email === $user->email, 403);
            if (! $account->hasVerifiedEmail()) {
                $account->markEmailAsVerified();
                $email = CustomerAccountEmail::where('user_id', $account->id)->where('metadata->kind', 'welcome')->first()
                    ?? $this->welcome->prepare($account);
            } else {
                $email = CustomerAccountEmail::where('user_id', $account->id)->where('metadata->kind', 'welcome')->where('status', 'failed')->first();
            }
            if ($email) {
                DB::afterCommit(fn () => $this->welcome->send($email));
            }
        });
        $user->refresh();
    }
}
