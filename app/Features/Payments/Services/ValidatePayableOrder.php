<?php

namespace App\Features\Payments\Services;

use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Orders\Models\Order;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ValidatePayableOrder
{
    public function execute(Order $order): void
    {
        app(FamilyAccess::class)->requirePurchasing(User::findOrFail($order->user_id));
        $metadata = $order->metadata;
        if (($metadata['source'] ?? null) !== 'customer_cart') {
            return;
        }

        if (($metadata['kind'] ?? null) === 'account_cart') {
            $this->validateAccountCart($order, $metadata['cart_groups'] ?? []);

            return;
        }

        $expected = $this->ids($metadata['cart_selection_ids'] ?? []);
        $current = match ($metadata['kind'] ?? null) {
            'competition' => $this->competitionSelectionIds($order),
            'concert' => $this->concertSelectionIds($order),
            default => [],
        };

        if ($expected === [] || $expected !== $current) {
            $this->cartChanged();
        }
    }

    private function validateAccountCart(Order $order, mixed $groups): void
    {
        if (! is_array($groups) || $groups === []) {
            $this->cartChanged();
        }
        foreach ($groups as $group) {
            if (! is_array($group)) {
                $this->cartChanged();
            }
            $expected = $this->ids($group['selection_ids'] ?? []);
            $current = match ($group['kind'] ?? null) {
                'competition' => $this->competitionGroupSelectionIds($order, $group['source_uuid'] ?? null),
                'concert' => $this->concertGroupSelectionIds($order, $group['source_uuid'] ?? null, ($group['scope'] ?? null) === 'studio'),
                default => [],
            };
            if ($expected === [] || $expected !== $current) {
                $this->cartChanged();
            }
        }
    }

    private function competitionGroupSelectionIds(Order $order, ?string $eventUuid): array
    {
        $event = SchedulingEvent::query()->where('uuid', $eventUuid)->first();

        return $event ? $this->ids(CustomerMediaSelection::query()->where('user_id', $order->user_id)
            ->where('scheduling_event_id', $event->id)->where('in_cart', true)->pluck('id')->all()) : [];
    }

    private function concertGroupSelectionIds(Order $order, ?string $concertUuid, bool $studioScope = false): array
    {
        return $this->ids(CustomerConcertPhotoSelection::query()->where('user_id', $order->user_id)
            ->where('in_cart', true)->whereHas($studioScope ? 'concert.studio' : 'concert', fn ($query) => $query->where('uuid', $concertUuid))
            ->pluck('id')->all());
    }

    private function cartChanged(): never
    {
        throw ValidationException::withMessages([
            'payment' => 'Your cart changed after this order was prepared. Return to the cart and review the updated order.',
        ]);
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
