<?php

namespace App\Features\Tracking\Services;

use App\Features\Tracking\Models\ConcertTrackerSetting;
use App\Features\Tracking\Models\StaffEmailSignature;
use App\Models\User;

class EmailBranding
{
    public function for(?int $userId): array
    {
        $settings = ConcertTrackerSetting::query()->first();
        $user = $userId ? User::query()->find($userId) : null;
        $signature = $user ? StaffEmailSignature::query()->where('user_id', $user->id)->where('is_enabled', true)->first() : null;

        $configuredLogo = $settings?->email_logo_url;

        return [
            'businessName' => $settings?->email_business_name ?: 'DancePro Photography & Video',
            'logoUrl' => $configuredLogo ? (str_starts_with($configuredLogo, '/') ? url($configuredLogo) : $configuredLogo) : asset('images/brand/dancepro-logo-inverse-landscape.png'),
            'phone' => $settings?->email_phone,
            'address' => $settings?->email_address,
            'website' => $settings?->email_website ?: 'https://www.dancepro.com.au',
            'replyAddress' => $settings?->email_reply_address,
            'legalFooter' => $settings?->email_legal_footer,
            'signature' => [
                'name' => $signature?->display_name ?: ($user?->name ?: 'DancePro Team'),
                'position' => $signature?->position,
                'phone' => $signature?->phone,
                'signOff' => $signature?->sign_off ?: 'Kind regards,',
            ],
        ];
    }
}
