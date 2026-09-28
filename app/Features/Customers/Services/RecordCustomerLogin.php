<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerLoginEvent;
use App\Models\User;
use Illuminate\Http\Request;

class RecordCustomerLogin
{
    public function execute(Request $request, string $email, ?User $user, bool $successful, string $channel = 'web'): void
    {
        if ($user && $user->type !== 'customer') {
            return;
        }
        if (! $user && ! User::where('type', 'customer')->whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
            return;
        }
        $user ??= User::where('type', 'customer')->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
        $recentFailures = CustomerLoginEvent::where('user_id', $user?->id)->where('successful', false)->where('occurred_at', '>=', now()->subHour())->count();
        $knownIp = $user && CustomerLoginEvent::where('user_id', $user->id)->where('successful', true)->where('ip_address', $request->ip())->exists();
        $reasons = array_values(array_filter([$recentFailures >= 4 ? 'repeated_failed_logins' : null, $successful && ! $knownIp ? 'new_ip_address' : null]));
        CustomerLoginEvent::create(['user_id' => $user?->id, 'email' => mb_strtolower($email), 'channel' => $channel, 'successful' => $successful, 'ip_address' => $request->ip(), 'device_name' => $request->input('device_name'), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000), 'risk_level' => $reasons === [] ? 'normal' : ($recentFailures >= 4 ? 'high' : 'review'), 'risk_reasons' => $reasons, 'occurred_at' => now()]);
    }
}
