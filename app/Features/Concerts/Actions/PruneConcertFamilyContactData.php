<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Models\ConcertFamilyShare;
use Illuminate\Support\Facades\DB;

class PruneConcertFamilyContactData
{
    public function execute(): int
    {
        $count = 0;
        ConcertFamilyShare::whereNull('contact_redacted_at')->where(fn ($q) => $q
            ->where('revoked_at', '<=', now()->subDays(180))
            ->orWhere('expires_at', '<=', now()->subDays(180))
            ->orWhere(fn ($q) => $q->whereNull('accepted_at')->where('invitation_expires_at', '<=', now()->subDays(180))))
            ->eachById(function (ConcertFamilyShare $share) use (&$count) {
                DB::transaction(function () use ($share, &$count) {
                    $share = ConcertFamilyShare::whereKey($share->id)->lockForUpdate()->firstOrFail();
                    $ended = $share->revoked_at ?? $share->expires_at ?? $share->invitation_expires_at;
                    if ($share->contact_redacted_at || $ended->gt(now()->subDays(180))) {
                        return;
                    }
                    $share->forceFill(['recipient_name' => 'Removed recipient', 'recipient_email' => 'removed-'.$share->uuid.'@invalid.test',
                        'contact_redacted_at' => now(), 'revoked_at' => $share->revoked_at ?? now(),
                        'code_hash' => null, 'challenge_session_hash' => null, 'verified_session_hash' => null])->save();
                    $count++;
                });
            });

        return $count;
    }
}
