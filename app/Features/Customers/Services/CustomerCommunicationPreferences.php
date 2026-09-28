<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerConsentHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerCommunicationPreferences
{
    public static function enabled(User $user, string $channel): bool
    {
        if ($channel === 'marketing_email') {
            return $user->customerProfile?->marketing_consent_at !== null;
        }

        $preferences = $user->customerProfile?->preferences ?? [];

        return (bool) ($preferences[$channel] ?? $preferences['download_email'] ?? true);
    }

    public function save(User $user, array $input, ?User $actor = null, string $source = 'customer_profile', ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $input, $actor, $source, $reason): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->anonymized_at || $user->merged_into_user_id, 409, 'A merged or anonymised account cannot be edited.');
            $profile = $user->customerProfile()->firstOrCreate(['user_id' => $user->id], ['registration_source' => $source]);
            $user->setRelation('customerProfile', $profile);
            $values = [
                'marketing_email' => (bool) ($input['marketing_email'] ?? false),
                'media_ready_email' => (bool) ($input['media_ready_email'] ?? $input['download_email'] ?? false),
                'expiry_reminders_email' => (bool) ($input['expiry_reminders_email'] ?? $input['download_email'] ?? false),
            ];
            foreach ($values as $channel => $granted) {
                if (self::enabled($user, $channel) !== $granted) {
                    CustomerConsentHistory::create([
                        'user_id' => $user->id, 'actor_user_id' => $actor?->id,
                        'channel' => $channel, 'granted' => $granted, 'source' => $source,
                        'reason' => $reason, 'snapshot' => $values,
                    ]);
                }
            }
            $preferences = collect($profile->preferences ?? [])->except(['service_email', 'download_email', 'gallery_email'])->all();
            $profile->update([
                'preferences' => array_merge($preferences, collect($values)->except('marketing_email')->all()),
                'marketing_consent_at' => $values['marketing_email'] ? ($profile->marketing_consent_at ?? now()) : null,
            ]);
        });
        $user->unsetRelation('customerProfile');
    }
}
