<?php

namespace App\Features\Downloads\Services;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Models\User;
use Illuminate\Support\Str;

class IssuePurchasedMediaDownload
{
    /** @return array{download_link: DownloadLink, token: string} */
    public function execute(User $user, OrderMediaEntitlement $entitlement, MediaAsset $asset): array
    {
        $token = Str::random(64);
        $link = DownloadLink::create([
            'uuid' => (string) Str::uuid(),
            'generated_by_user_id' => $user->id,
            'media_collection_id' => $asset->media_collection_id,
            'media_asset_id' => $asset->id,
            'order_item_id' => $entitlement->order_item_id,
            'storage_disk' => $asset->storage_disk,
            'storage_key' => $asset->storage_key,
            'original_filename' => $asset->original_filename ?: basename($asset->storage_key),
            'purpose' => 'customer_purchase',
            'token_hash' => hash('sha256', $token),
            'status' => DownloadLinkStatus::ACTIVE,
            'expires_at' => now()->addMinutes(5),
            'notes' => 'Short-lived handoff from an authenticated media entitlement.',
        ]);

        return ['download_link' => $link, 'token' => $token];
    }
}
