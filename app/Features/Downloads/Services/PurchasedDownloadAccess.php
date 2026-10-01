<?php

namespace App\Features\Downloads\Services;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchasedDownloadAccess
{
    public function __construct(private readonly AuthorizePurchasedMediaDownload $authorize) {}

    /** Recheck short-lived handoffs after family removal, refund or expiry. */
    public function allows(DownloadLink $link): bool
    {
        if ($link->purpose !== 'customer_purchase') {
            return true;
        }
        $viewer = $link->generatedBy;
        $asset = MediaAsset::find($link->media_asset_id);
        if (! $viewer?->canAccessCustomer() || ! $asset) {
            return false;
        }
        $entitlements = OrderMediaEntitlement::where('order_item_id', $link->order_item_id)
            ->whereHas('assets', fn ($query) => $query->where('media_assets.id', $asset->id))->get();
        foreach ($entitlements as $entitlement) {
            try {
                $this->authorize->execute($viewer, $entitlement, $asset);

                return true;
            } catch (HttpException) {
                // Another entitlement may cover the same asset; none may bypass the existing checks.
            }
        }

        return false;
    }
}
