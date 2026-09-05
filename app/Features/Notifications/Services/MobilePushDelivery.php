<?php

namespace App\Features\Notifications\Services;

use App\Features\Notifications\Models\MobilePushDevice;
use App\Features\Scheduling\Models\CrewNotification;
use Illuminate\Support\Facades\Http;

class MobilePushDelivery
{
    public function deliver(int $notificationId): void
    {
        $gateway = config('mobile.push.gateway_url');
        if (! filled($gateway)) {
            return;
        }

        $notification = CrewNotification::query()->find($notificationId);
        if (! $notification) {
            return;
        }

        MobilePushDevice::query()->where('user_id', $notification->user_id)->whereNull('disabled_at')
            ->each(function (MobilePushDevice $device) use ($gateway, $notification): void {
                $usesNotificationContent = $notification->type === 'direct_message';
                $response = Http::withToken((string) config('mobile.push.gateway_token'))
                    ->timeout(10)->post($gateway, [
                        'platform' => $device->platform,
                        'environment' => $device->environment,
                        'push_token' => $device->push_token,
                        'title' => $usesNotificationContent ? $notification->title : 'DancePro update',
                        'body' => $usesNotificationContent ? $notification->message : 'Open DancePro to view this update.',
                        'destination' => $notification->destination(),
                    ]);

                if (in_array($response->status(), [404, 410], true)) {
                    $device->update(['disabled_at' => now()]);
                }

                if (! in_array($response->status(), [200, 201, 202, 204, 404, 410], true)) {
                    $response->throw();
                }
            });
    }
}
