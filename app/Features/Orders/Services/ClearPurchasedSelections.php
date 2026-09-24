<?php

namespace App\Features\Orders\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;

class ClearPurchasedSelections
{
    public function execute(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Fulfilled], true)
            || ! $order->paid_at || ($order->metadata['source'] ?? null) !== 'customer_cart') {
            return;
        }

        $metadata = $order->metadata;
        $selectionIds = $this->ids($metadata['cart_selection_ids'] ?? []);
        if ($selectionIds === []) {
            return;
        }

        if (($metadata['kind'] ?? null) === 'competition') {
            $event = SchedulingEvent::where('uuid', $metadata['source_uuid'] ?? null)->first();
            if ($event) {
                CustomerMediaSelection::where('user_id', $order->user_id)
                    ->where('scheduling_event_id', $event->id)
                    ->whereIn('id', $selectionIds)->delete();
            }
        } elseif (($metadata['kind'] ?? null) === 'concert') {
            $studio = Studio::where('uuid', $metadata['source_uuid'] ?? null)->first();
            if ($studio) {
                CustomerConcertPhotoSelection::where('user_id', $order->user_id)
                    ->where('studio_id', $studio->id)
                    ->whereIn('id', $selectionIds)->delete();
            }
        }
    }

    private function ids(mixed $value): array
    {
        return is_array($value) ? array_values(array_unique(array_filter($value, 'is_int'))) : [];
    }
}
