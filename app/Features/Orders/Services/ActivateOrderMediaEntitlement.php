<?php

namespace App\Features\Orders\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Services\CustomerMediaNotifications;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateOrderMediaEntitlement
{
    public function __construct(private readonly CustomerMediaNotifications $notifications) {}

    /**
     * A product's assets are frozen when its media type first becomes ready.
     * An empty video set records a pending entitlement without starting its clock.
     *
     * @param  Collection<int, MediaAsset>  $assets
     */
    public function execute(OrderItem $item, string $mediaType, Collection $assets, ?CarbonInterface $becameReadyAt = null, bool $notifyCustomer = false): OrderMediaEntitlement
    {
        if (! in_array($mediaType, ['photo', 'video'], true)) {
            throw ValidationException::withMessages(['media_type' => 'Only photo and video download periods are supported.']);
        }
        $item->loadMissing('order');
        if (! in_array($item->order->status, [OrderStatus::Paid, OrderStatus::Fulfilled], true) || ! $item->order->paid_at) {
            throw ValidationException::withMessages(['order' => 'Download access can start only after payment.']);
        }
        if ($assets->isEmpty() && $mediaType !== 'video') {
            throw ValidationException::withMessages(['assets' => 'A photo purchase needs its exact ready photos.']);
        }
        if ($assets->contains(fn (MediaAsset $asset) => $asset->media_type->value !== $mediaType
            || $asset->status->value !== 'available' || ! $asset->is_visible
            || ($item->media_asset_id !== null && $asset->id !== $item->media_asset_id)
            || ($item->media_collection_id !== null && $asset->media_collection_id !== $item->media_collection_id))) {
            throw ValidationException::withMessages(['assets' => 'Every purchased asset must be available and belong to this order item.']);
        }
        if ($item->media_asset_id === null && $item->media_collection_id === null
            && ! ($mediaType === 'video' && $assets->isEmpty() && ($item->metadata['media_pending'] ?? false))) {
            throw ValidationException::withMessages(['order' => 'The purchased media source is missing.']);
        }
        $snapshot = $item->metadata['asset_uuids'] ?? null;
        if (is_array($snapshot) && ! ($item->metadata['media_pending'] ?? false)
            && collect($snapshot)->sort()->values()->all() !== $assets->pluck('uuid')->sort()->values()->all()) {
            throw ValidationException::withMessages(['assets' => 'The ready files do not match this order’s purchased media snapshot.']);
        }

        return DB::transaction(function () use ($item, $mediaType, $assets, $becameReadyAt, $notifyCustomer): OrderMediaEntitlement {
            $entitlement = OrderMediaEntitlement::firstOrCreate([
                'order_item_id' => $item->id, 'media_type' => $mediaType,
            ]);
            if ($entitlement->ready_at !== null || $assets->isEmpty()) {
                return $entitlement->load('assets');
            }
            $ready = CarbonImmutable::instance($becameReadyAt ?? now());
            $paid = CarbonImmutable::instance($item->order->paid_at);
            if ($ready->lessThan($paid)) {
                $ready = $paid;
            }
            $entitlement->assets()->sync($assets->pluck('id')->all());
            $entitlement->update(['ready_at' => $ready,
                'expires_at' => $mediaType === 'photo' ? $ready->addYear() : $ready->addDays(60)]);
            if ($notifyCustomer) {
                $this->notifications->ready($entitlement->fresh());
            }

            return $entitlement->load('assets');
        });
    }
}
