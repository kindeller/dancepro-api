<?php

namespace App\Features\Orders\Observers;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\ClearPurchasedSelections;

class OrderObserver
{
    public function __construct(private readonly ClearPurchasedSelections $clearPurchasedSelections) {}

    public function updated(Order $order): void
    {
        if ($order->wasChanged(['status', 'paid_at'])) {
            $this->clearPurchasedSelections->execute($order);
        }
    }
}
