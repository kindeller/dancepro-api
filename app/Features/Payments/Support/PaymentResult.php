<?php

namespace App\Features\Payments\Support;

readonly class PaymentResult
{
    public function __construct(
        public bool $approved,
        public ?string $transactionId = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
