<?php

namespace App\Features\Orders\Services;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderFinancials
{
    public function event(Order $order, OrderItem $item): ?array
    {
        $kind = data_get($item->metadata, 'event_kind');
        $uuid = data_get($item->metadata, 'event_uuid');
        if (in_array($kind, ['competition', 'concert'], true) && $uuid) {
            return [$kind, $uuid];
        }
        if ($uuid = data_get($item->metadata, 'concert_uuid') ?: $item->mediaCollection?->concert?->uuid) {
            return ['concert', $uuid];
        }
        $kind = data_get($order->metadata, 'kind');
        $uuid = data_get($order->metadata, 'source_uuid');
        if ($uuid && ($kind === 'competition' || ($kind === 'concert' && $this->complimentary($order)))) {
            return [$kind, $uuid];
        }

        return null;
    }

    public function complimentary(Order $order): bool
    {
        return data_get($order->metadata, 'source') === 'complimentary_media_grant';
    }

    public function cash(OrderItem $item): int
    {
        return (int) data_get($item->metadata, 'net_after_account_credit', $item->total_price_amount);
    }

    public function refunds(Order $order, ?Collection $items = null): int
    {
        $order->loadMissing('refunds.items');
        $refunds = $order->refunds->where('status', 'completed');

        return $items === null ? (int) $refunds->sum('amount')
            : (int) $refunds->flatMap->items->whereIn('order_item_id', $items->pluck('id'))->sum('amount');
    }

    /** Return an in-memory report projection; never save it as an order. */
    public function forReport(Order $order, ?string $kind = null): ?Order
    {
        $order->loadMissing('items.mediaCollection.concert', 'refunds.items');
        $items = $order->items;
        if ($kind) {
            $items = $items->filter(fn (OrderItem $item): bool => ($this->event($order, $item)[0] ?? data_get($order->metadata, 'kind')) === $kind);
            if ($items->isEmpty()) {
                return null;
            }
        }
        $projection = clone $order;
        $projection->setRelation('items', $items);
        $scoped = $kind !== null && $items->count() !== $order->items->count();
        $cash = $scoped ? (int) $items->sum(fn (OrderItem $item): int => $this->cash($item)) : $order->paymentAmount();
        $credit = $scoped ? (int) $items->sum(fn (OrderItem $item): int => (int) data_get($item->metadata, 'account_credit_amount', 0)) : (int) data_get($order->metadata, 'account_credit_amount', 0);
        $gross = $scoped ? (int) $items->sum(fn (OrderItem $item): int => $item->unit_price_amount * $item->quantity) : $order->subtotal_amount;
        $metadata = $order->metadata ?? [];
        $metadata['payment_amount'] = $cash;
        $metadata['account_credit_amount'] = $credit;
        $metadata['report_refunds'] = $this->refunds($order, $scoped ? $items : null);
        $metadata['report_net_receipts'] = $cash - $metadata['report_refunds'];
        if ($scoped) {
            $metadata['discount_amount'] = max(0, $gross - $cash - $credit);
            $groups = collect(data_get($order->metadata, 'cart_groups', []))->where('kind', $kind);
            $metadata['bulk_discount_amount'] = (int) $groups->sum('bulk_discount_amount');
            $metadata['discount_code_amount'] = (int) $groups->sum('discount_code_amount');
        }
        $projection->fill(['total_amount' => $cash, 'subtotal_amount' => $gross, 'metadata' => $metadata,
            'tax_amount' => $scoped ? (int) $items->sum('tax_amount') : $order->tax_amount]);

        return $projection;
    }
}
