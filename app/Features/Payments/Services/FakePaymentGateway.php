<?php

namespace App\Features\Payments\Services;

use App\Features\Orders\Models\Order;
use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\Support\PaymentResult;

class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function charge(Order $order, string $idempotencyKey, string $testOutcome): PaymentResult
    {
        if ($testOutcome === 'declined') {
            return new PaymentResult(false, failureCode: 'test_decline', failureMessage: 'The test payment was declined.');
        }

        return new PaymentResult(true, 'fake_'.str_replace('-', '', $idempotencyKey));
    }
}
