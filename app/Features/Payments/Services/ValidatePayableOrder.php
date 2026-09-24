<?php

namespace App\Features\Payments\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Orders\Models\Order;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use Illuminate\Validation\ValidationException;

class ValidatePayableOrder
{
    public function execute(Order $order): void
    {
        $metadata = $order->metadata;
        if (($metadata['source'] ?? null) !== 'customer_cart') {
            return;
        }

        $expected = $this->ids($metadata['cart_selection_ids'] ?? []);
        $current = match ($metadata['kind'] ?? null) {
            'competition' => $this->competitionSelectionIds($order),
            'concert' => $this->concertSelectionIds($order),
            default => [],
        };

        if ($expected === [] || $expected !== $current) {
            throw ValidationException::withMessages([
                'payment' => 'Your cart changed after this order was prepared. Return to the cart and review the updated order.',
            ]);
        }
    }

    private function competitionSelectionIds(Order $order): array
    {
        $event = SchedulingEvent::query()->where('uuid', $order->metadata['source_uuid'] ?? null)->first();

        return $event ? $this->ids(CustomerMediaSelection::query()
            ->where('user_id', $order->user_id)->where('scheduling_event_id', $event->id)
            ->where('in_cart', true)->pluck('id')->all()) : [];
    }

    private function concertSelectionIds(Order $order): array
    {
        $studio = Studio::query()->where('uuid', $order->metadata['source_uuid'] ?? null)->first();

        return $studio ? $this->ids(CustomerConcertPhotoSelection::query()
            ->where('user_id', $order->user_id)->where('studio_id', $studio->id)
            ->where('in_cart', true)->pluck('id')->all()) : [];
    }

    private function ids(array $ids): array
    {
        sort($ids);

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
