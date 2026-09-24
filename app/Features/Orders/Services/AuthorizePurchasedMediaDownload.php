<?php

namespace App\Features\Orders\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Support\OrderStatus;
use App\Models\User;

class AuthorizePurchasedMediaDownload
{
    public function execute(User $user, OrderMediaEntitlement $entitlement, MediaAsset $asset): MediaAsset
    {
        $entitlement = OrderMediaEntitlement::with('orderItem.order')->findOrFail($entitlement->id);
        $order = $entitlement->orderItem?->order;
        abort_unless($order && $order->user_id === $user->id
            && in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled], true)
            && $order->paid_at !== null, 404);

        abort_unless($entitlement->assets()->where('media_assets.id', $asset->id)->exists(), 404);

        abort_unless($entitlement->ready_at !== null, 404);
        abort_if($entitlement->expires_at === null || $entitlement->expires_at->isPast(), 410,
            'This download period has ended.');

        $asset = MediaAsset::query()->with('collection')->whereKey($asset->id)
            ->where('media_type', $entitlement->media_type)
            ->where('status', 'available')
            ->firstOrFail();
        if (($asset->collection?->metadata['source'] ?? null) === 'competition_video_upload') {
            abort_unless($asset->is_visible && $asset->collection->status->value === 'published'
                && in_array($asset->collection->visibility->value, ['customer', 'public'], true), 404);
        }

        return $asset;
    }
}
