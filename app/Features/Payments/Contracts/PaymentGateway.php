<?php

namespace App\Features\Payments\Contracts;

use App\Features\Orders\Models\Order;
use App\Features\Payments\Support\PaymentResult;

interface PaymentGateway
{
    public function name(): string;

    public function charge(Order $order, string $idempotencyKey, string $testOutcome): PaymentResult;
}
