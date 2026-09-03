<?php

namespace App\Features\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Str;

class RotateUserPassword
{
    public function execute(User $user, string $password, bool $verifyEmail = false, ?int $preserveTokenId = null): User
    {
        $attributes = [
            'password' => $password,
            'remember_token' => Str::random(60),
        ];

        if ($verifyEmail && $user->email_verified_at === null) {
            $attributes['email_verified_at'] = now();
        }

        $user->forceFill($attributes)->save();
        $user->tokens()
            ->when($preserveTokenId !== null, fn ($query) => $query->whereKeyNot($preserveTokenId))
            ->delete();

        return $user;
    }
}
