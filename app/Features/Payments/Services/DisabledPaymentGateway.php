<?php

namespace App\Features\Payments\Services;

use App\Features\Orders\Models\Order;
use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\Support\PaymentResult;
use Illuminate\Validation\ValidationException;

class DisabledPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'disabled';
    }

    public function charge(Order $order, string $idempotencyKey, string $testOutcome): PaymentResult
    {
        throw ValidationException::withMessages(['payment' => 'Checkout is not enabled in this environment.']);
    }
}
