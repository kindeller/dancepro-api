<?php

namespace App\Features\StudioBilling\Services;

use App\Features\StudioBilling\Models\StudioInvoice;

class ReconcileStudioInvoicePayments
{
    public function __construct(private readonly StudioInvoiceGateway $gateway, private readonly PayStudioInvoice $payments) {}

    public function execute(): int
    {
        if ($this->gateway->mode() !== 'braintree') {
            return 0;
        }
        $count = 0;
        StudioInvoice::where('status', 'issued')->where('is_test', $this->gateway->isTest())
            ->whereHas('payments', fn ($query) => $query->where('provider', 'braintree')->whereIn('status', ['processing', 'unknown', 'submitted']))
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $this->payments->reconcile($invoice);
                    $count++;
                }
            });

        return $count;
    }
}
