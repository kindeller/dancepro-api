<?php

namespace App\Features\Media\Actions;

use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaIngestEvent;
use App\Models\User;

class RecordMediaIngestEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        User $user,
        ?MediaAsset $asset,
        string $action,
        array $context = [],
        bool $wasSuccessful = true,
    ): MediaIngestEvent {
        return MediaIngestEvent::query()->create([
            'user_id' => $user->id,
            'media_asset_id' => $asset?->id,
            'action' => $action,
            'was_successful' => $wasSuccessful,
            'context' => ['token_id' => $user->currentAccessToken()?->getKey()] + $context,
        ]);
    }
}
