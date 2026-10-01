<?php

namespace App\Features\StudioBilling\Jobs;

use App\Features\StudioBilling\Services\StudioPaymentReceipts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendStudioPaymentReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $paymentId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(StudioPaymentReceipts $receipts): void
    {
        $receipts->send($this->paymentId);
    }
}
