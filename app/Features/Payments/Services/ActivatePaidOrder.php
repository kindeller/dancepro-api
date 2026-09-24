<?php

namespace App\Features\Payments\Services;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\ActivateOrderMediaEntitlement;
use Illuminate\Support\Collection;

class ActivatePaidOrder
{
    public function __construct(private readonly ActivateOrderMediaEntitlement $activate) {}

    public function execute(Order $order): void
    {
        $order->load('items');
        foreach ($order->items as $item) {
            $uuids = $item->metadata['asset_uuids'] ?? [];
            /** @var Collection<int, MediaAsset> $assets */
            $assets = MediaAsset::query()->whereIn('uuid', $uuids)->get();
            $this->activate->execute($item, $item->item_type, $assets);
        }
    }
}
