<?php

namespace App\Features\Studios\Actions;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Studios\Notifications\StudioAccountInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class ManageStudioAccountAccess
{
    public function invite(Studio $studio, User $actor, array $data): StudioAccountAccess
    {
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $active = $user?->hasVerifiedEmail() ?? false;
        if ($active) {
            CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now()]);
        }

        $access = StudioAccountAccess::updateOrCreate(['studio_id' => $studio->id, 'email' => $email], [
            'user_id' => $user?->id, 'role' => $data['role'], 'status' => $active ? 'active' : 'pending',
            'invited_by_user_id' => $actor->id, 'invited_at' => now(), 'activated_at' => $active ? now() : null, 'revoked_at' => null,
        ]);
        Notification::route('mail', $email)->notify(new StudioAccountInvitation($studio, $user !== null));

        return $access;
    }

    public function revoke(StudioAccountAccess $access): void
    {
        $access->update(['status' => 'revoked', 'revoked_at' => now()]);
    }
}
