<?php

namespace App\Features\Reporting\Services;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MediaSalesReport
{
    public function report(array $filters): array
    {
        $orders = $this->query($filters)->with('items')->orderBy('paid_at')->get();
        $previous = $this->previousOrders($filters);

        return [
            'summary' => $this->summary($orders),
            'previousSummary' => $previous === null ? null : $this->summary($previous),
            'customers' => $this->customers($orders, $filters),
            'products' => $this->products($orders),
            'events' => $this->events($orders),
            'discounts' => $this->discounts($orders),
            'credit' => $this->credit(),
        ];
    }

    public function query(array $filters): Builder
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])
            ->where('metadata->source', 'customer_cart')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('paid_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('paid_at', '<=', $date.' 23:59:59'))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('metadata->kind', $kind))
            ->when($filters['customer'] ?? null, function (Builder $query, string $customer): void {
                $query->where(function (Builder $query) use ($customer): void {
                    $query->where('customer_email', 'like', '%'.$customer.'%')
                        ->orWhere('customer_name', 'like', '%'.$customer.'%');
                });
            });
    }

    private function previousOrders(array $filters): ?Collection
    {
        if (empty($filters['date_from']) || empty($filters['date_to'])) {
            return null;
        }

        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $days = $from->diffInDays($to) + 1;
        $previousFilters = $filters;
        $previousFilters['date_to'] = $from->subDay()->toDateString();
        $previousFilters['date_from'] = $from->subDays($days)->toDateString();

        return $this->query($previousFilters)->with('items')->get();
    }

    private function summary(Collection $orders): array
    {
        $customers = $orders->map(fn (Order $order): string => $this->customerKey($order))->unique();
        $total = $orders->sum('total_amount');

        return [
            'orders' => $orders->count(),
            'sales' => $total,
            'customers' => $customers->count(),
            'average_order' => $orders->isEmpty() ? 0 : (int) round($total / $orders->count()),
            'median_order' => $this->median($orders->pluck('total_amount')->map(fn ($amount) => (int) $amount)->sort()->values()),
            'tax' => $orders->sum('tax_amount'),
            'discounts' => $orders->sum(fn (Order $order): int => (int) data_get($order->metadata, 'discount_amount', 0)),
            'credit' => $orders->sum(fn (Order $order): int => (int) data_get($order->metadata, 'account_credit_amount', 0)),
            'items' => $orders->sum(fn (Order $order): int => $order->items->sum('quantity')),
        ];
    }

    private function customers(Collection $orders, array $filters): array
    {
        $groups = $orders->groupBy(fn (Order $order): string => $this->customerKey($order));
        $returningKeys = collect();
        if (! empty($filters['date_from'])) {
            $candidateIds = $orders->pluck('user_id')->filter()->unique();
            $candidateEmails = $orders->pluck('customer_email')->filter()->map(fn ($email) => mb_strtolower(trim($email)))->unique();
            $returningKeys = Order::query()
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value])
                ->where('metadata->source', 'customer_cart')->where('paid_at', '<', $filters['date_from'].' 00:00:00')
                ->where(function (Builder $query) use ($candidateIds, $candidateEmails): void {
                    $query->whereIn('user_id', $candidateIds)->orWhereIn('customer_email', $candidateEmails);
                })->get(['user_id', 'customer_email'])->map(fn (Order $order): string => $this->customerKey($order))->unique();
        }
        $returning = $groups->keys()->intersect($returningKeys);
        $repeat = $groups->filter(fn (Collection $group): bool => $group->count() > 1);
        $top = $groups->map(function (Collection $group): array {
            $order = $group->last();

            return ['name' => $order->customer_name ?: 'Customer', 'email' => $order->customer_email,
                'orders' => $group->count(), 'sales' => $group->sum('total_amount'), 'last_order' => $order->paid_at];
        })->sortByDesc('sales')->take(25)->values();

        return [
            'new' => $groups->count() - $returning->count(),
            'returning' => $returning->count(),
            'repeat' => $repeat->count(),
            'repeat_sales' => $repeat->flatten(1)->sum('total_amount'),
            'top' => $top,
            'classification_available' => ! empty($filters['date_from']),
        ];
    }

    private function products(Collection $orders): Collection
    {
        return $orders->flatMap->items->groupBy(function ($item): string {
            return (string) data_get($item->metadata, 'product', $item->item_type ?: 'media');
        })->map(function (Collection $items, string $product): array {
            return ['key' => $product, 'label' => $this->productLabel($product),
                'quantity' => $items->sum('quantity'), 'sales' => $items->sum('total_price_amount'),
                'orders' => $items->pluck('order_id')->unique()->count()];
        })->sortByDesc('sales')->values();
    }

    private function events(Collection $orders): Collection
    {
        $eventUuids = $orders->filter(fn (Order $order) => data_get($order->metadata, 'kind') === 'competition')
            ->pluck('metadata.source_uuid')->filter()->unique();
        $studioUuids = $orders->filter(fn (Order $order) => data_get($order->metadata, 'kind') === 'concert')
            ->pluck('metadata.source_uuid')->filter()->unique();
        $events = SchedulingEvent::query()->whereIn('uuid', $eventUuids)->pluck('name', 'uuid');
        $studios = Studio::query()->whereIn('uuid', $studioUuids)->pluck('name', 'uuid');

        return $orders->groupBy(function (Order $order): string {
            return data_get($order->metadata, 'kind', 'unknown').'|'.data_get($order->metadata, 'source_uuid', 'unknown');
        })->map(function (Collection $group, string $key) use ($events, $studios): array {
            [$kind, $uuid] = explode('|', $key, 2);
            $name = $kind === 'competition' ? $events->get($uuid) : $studios->get($uuid);

            return ['kind' => $kind, 'name' => $name ?: 'Unknown or removed source', 'orders' => $group->count(),
                'customers' => $group->map(fn (Order $order) => $this->customerKey($order))->unique()->count(),
                'sales' => $group->sum('total_amount')];
        })->sortByDesc('sales')->values();
    }

    private function discounts(Collection $orders): array
    {
        return [
            'bulk' => $orders->sum(fn (Order $order): int => (int) data_get($order->metadata, 'bulk_discount_amount', 0)),
            'codes' => $orders->sum(fn (Order $order): int => (int) data_get($order->metadata, 'discount_code_amount', 0)),
            'credit_used' => $orders->sum(fn (Order $order): int => (int) data_get($order->metadata, 'account_credit_amount', 0)),
            'discounted_orders' => $orders->filter(fn (Order $order): bool => (int) data_get($order->metadata, 'discount_amount', 0) > 0)->count(),
            'code_orders' => $orders->filter(fn (Order $order): bool => (int) data_get($order->metadata, 'discount_code_amount', 0) > 0)->count(),
            'credit_orders' => $orders->filter(fn (Order $order): bool => (int) data_get($order->metadata, 'account_credit_amount', 0) > 0)->count(),
        ];
    }

    private function credit(): array
    {
        return [
            'balance' => CustomerCreditTransaction::query()->sum('amount'),
            'granted' => CustomerCreditTransaction::query()->where('amount', '>', 0)->sum('amount'),
            'used' => abs((int) CustomerCreditTransaction::query()->where('amount', '<', 0)->sum('amount')),
            'customers' => CustomerCreditTransaction::query()->distinct()->count('user_id'),
        ];
    }

    private function customerKey(Order $order): string
    {
        return $order->user_id ? 'user:'.$order->user_id : 'email:'.mb_strtolower(trim((string) $order->customer_email));
    }

    private function median(Collection $values): int
    {
        $count = $values->count();
        if ($count === 0) {
            return 0;
        }
        $middle = intdiv($count, 2);

        return $count % 2 ? $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function productLabel(string $product): string
    {
        return match ($product) {
            'photo', 'competition_photo' => 'Individual photo',
            'routine_photos' => 'All routine photos',
            'routine_video' => 'Routine video',
            'routine_package' => 'Routine package',
            'concert_photo' => 'Concert photo',
            default => str($product)->replace('_', ' ')->title()->toString(),
        };
    }
}
