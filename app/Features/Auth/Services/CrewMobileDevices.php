<?php

namespace App\Features\Auth\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class CrewMobileDevices
{
    public function for(User $user): array
    {
        $currentId = $user->currentAccessToken()?->getKey();

        return $this->tokens($user)->map(fn (PersonalAccessToken $token): array => [
            'id' => $this->publicId($token),
            'name' => str($token->name)->after('crew-mobile:')->toString(),
            'current' => $token->getKey() === $currentId,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ])->all();
    }

    public function revoke(User $user, string $publicId): bool
    {
        $token = $this->tokens($user)->first(fn (PersonalAccessToken $token): bool => hash_equals($this->publicId($token), $publicId));

        return $token?->delete() ?? false;
    }

    /** @return Collection<int, PersonalAccessToken> */
    private function tokens(User $user): Collection
    {
        return $user->tokens()->where('name', 'like', 'crew-mobile:%')->latest('id')->get();
    }

    private function publicId(PersonalAccessToken $token): string
    {
        return hash_hmac('sha256', (string) $token->getKey(), (string) config('app.key'));
    }
}
