<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Models\User;

class RevokeDownloadLink
{
    public function handle(DownloadLink $downloadLink, User $user, ?string $reason = null): DownloadLink
    {
        $downloadLink->forceFill([
            'status' => DownloadLinkStatus::REVOKED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->id,
            'revoke_reason' => $reason,
        ])->save();

        return $downloadLink->refresh();
    }
}
