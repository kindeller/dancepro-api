<?php

namespace Tests\Concerns;

use App\Models\User;

trait AuthenticatesApiTokens
{
    /** @param list<string> $abilities */
    protected function actingAsApiUser(User $user, array $abilities): void
    {
        // Exercise persisted abilities, which Sanctum::actingAs does not populate.
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('Feature test', $abilities)->plainTextToken);
    }
}
