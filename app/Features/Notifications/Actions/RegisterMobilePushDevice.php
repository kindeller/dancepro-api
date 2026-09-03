<?php

namespace App\Features\Notifications\Actions;

use App\Features\Notifications\Models\MobilePushDevice;
use App\Models\User;

class RegisterMobilePushDevice
{
    public function execute(User $user, array $data): MobilePushDevice
    {
        $device = MobilePushDevice::query()->firstOrNew([
            'user_id' => $user->id,
            'installation_id' => $data['installation_id'],
        ]);
        $device->fill($data + [
            'push_token_hash' => hash('sha256', $data['push_token']),
            'last_registered_at' => now(),
            'disabled_at' => null,
        ])->save();

        return $device->refresh();
    }
}
