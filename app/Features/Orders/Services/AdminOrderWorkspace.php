<?php

namespace App\Features\Orders\Services;

use App\Features\Orders\Models\Order;

class AdminOrderWorkspace
{
    public function build(array $filters): array
    {
        $activeTab = $filters['tab'] ?? 'orders';
        $query = Order::query()->with('user')->withCount('items')
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['source'] ?? null, fn ($q, $value) => $q->where('metadata->source', $value))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('customer_email', 'like', '%'.$term.'%')->orWhere('customer_name', 'like', '%'.$term.'%')->orWhere('uuid', $term);
                    if ($id = Order::idFromDisplayNumber($term)) {
                        $q->orWhere('id', $id);
                    }
                });
            });
        if ($activeTab === 'refunds') {
            $query->whereHas('refunds')->with('refunds');
        } elseif ($activeTab === 'issues') {
            $query->whereNull('paid_at')->whereNull('cancelled_at')->whereHas('paymentAttempts', fn ($q) => $q->whereIn('status', ['failed', 'declined']))->with('paymentAttempts');
        }
        $orders = $query->latest()->paginate(30)->withQueryString();

        return compact('activeTab', 'filters', 'orders');
    }
}
