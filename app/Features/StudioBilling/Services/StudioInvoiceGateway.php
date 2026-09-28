<?php

namespace App\Features\StudioBilling\Services;

use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use Braintree\Gateway;
use Braintree\TransactionSearch;
use Illuminate\Validation\ValidationException;

class StudioInvoiceGateway
{
    public function mode(): string
    {
        $driver = config('studio-billing.driver');
        if ($driver === 'fake' && app()->environment(['local', 'testing'])) {
            return 'fake';
        }
        if ($driver === 'braintree' && count(array_filter(config('studio-billing.braintree'))) == 4 && filled(config('studio-billing.merchant_account_id'))) {
            return 'braintree';
        }

        return 'disabled';
    }

    public function isTest(): bool
    {
        return $this->mode() === 'fake' || config('studio-billing.braintree.environment') !== 'production';
    }

    public function available(StudioInvoice $invoice): bool
    {
        return $this->mode() !== 'disabled' && $invoice->is_test === $this->isTest();
    }

    public function token(StudioInvoice $invoice): string
    {
        if (! $this->available($invoice) || $this->mode() !== 'braintree') {
            abort(503, 'Card payments are not configured for this invoice.');
        }

        return $this->gateway()->clientToken()->generate(['merchantAccountId' => config('studio-billing.merchant_account_id')]);
    }

    public function charge(StudioInvoicePayment $payment, string $nonce): array
    {
        if ($this->mode() === 'fake') {
            return ['status' => 'succeeded', 'transaction' => 'test_'.$payment->uuid, 'message' => 'Simulated payment. No money was charged.'];
        }
        $result = $this->gateway()->transaction()->sale(['amount' => number_format($payment->amount_cents / 100, 2, '.', ''), 'paymentMethodNonce' => $nonce, 'merchantAccountId' => config('studio-billing.merchant_account_id'), 'orderId' => $payment->uuid, 'options' => ['submitForSettlement' => true, 'threeDSecure' => ['required' => true]]]);
        if (isset($result->transaction)) {
            return $this->normalise($result->transaction, $payment);
        }

        return ['status' => 'failed', 'transaction' => null, 'message' => 'The payment was not accepted. Please check your card or contact us.'];
    }

    public function reconcile(StudioInvoicePayment $payment): array
    {
        if ($payment->provider !== 'braintree' || $this->mode() !== 'braintree' || $payment->is_test !== $this->isTest()) {
            throw ValidationException::withMessages(['invoice' => 'The matching Braintree environment must be configured to check this payment.']);
        }
        if ($payment->provider_transaction_id) {
            return $this->normalise($this->gateway()->transaction()->find($payment->provider_transaction_id), $payment);
        }
        $matches = $this->gateway()->transaction()->search([TransactionSearch::orderId()->is($payment->uuid)]);
        if ((int) $matches->maximumCount() !== 1) {
            return ['status' => 'unknown', 'transaction' => null, 'message' => 'Payment outcome is still uncertain. Contact support before trying again.'];
        }

        return $this->normalise($matches->firstItem(), $payment);
    }

    public function normalise(object $transaction, StudioInvoicePayment $payment): array
    {
        if ($transaction->orderId !== $payment->uuid || $transaction->currencyIsoCode !== 'AUD' || StudioInvoices::cents((string) $transaction->amount) !== $payment->amount_cents) {
            throw new \RuntimeException('Invoice payment verification failed.');
        }
        $status = match ($transaction->status) {
            'settled' => 'succeeded',
            'submitted_for_settlement','settling','settlement_pending' => 'submitted',
            'processor_declined','gateway_rejected','failed','voided','settlement_declined' => 'failed',
            default => 'unknown',
        };

        return ['status' => $status, 'transaction' => $transaction->id, 'message' => match ($status) {
            'succeeded' => 'Payment settled.','submitted' => 'Payment accepted and awaiting settlement.','failed' => 'Payment failed or was declined.',default => 'Payment requires verification. Please do not pay again.'
        }];
    }

    protected function gateway(): Gateway
    {
        return new Gateway(config('studio-billing.braintree'));
    }
}
