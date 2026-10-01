<?php

namespace App\Features\StudioBilling\Services;

use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PayStudioInvoice
{
    public function __construct(private readonly StudioInvoiceGateway $gateway, private readonly StudioInvoices $invoices) {}

    public function pay(StudioInvoice $invoice, User $actor, array $data): StudioInvoicePayment
    {
        [$payment,$created] = $this->invoices->locked($invoice, function ($invoice) use ($actor, $data) {
            $previous = StudioInvoicePayment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($previous) {
                $this->check($previous->studio_invoice_id === $invoice->id && $previous->user_id === $actor->id, 'This payment reference is not valid for this invoice.');

                return [$previous, false];
            }
            $this->check($invoice->status === 'issued' && $invoice->balanceCents() > 0, 'There is no payable balance on this invoice.');
            $this->check(! $invoice->hasPendingPayment(), 'A payment is already being processed. Check its status before trying again.');
            $this->check($this->gateway->available($invoice), 'Online payments are not configured for this invoice.');
            $this->check((int) $data['balance_cents'] === $invoice->balanceCents(), 'The invoice balance has changed. Reload before paying.');
            $payment = $invoice->payments()->create(['idempotency_key' => $data['idempotency_key'], 'user_id' => $actor->id, 'provider' => $this->gateway->mode(), 'status' => 'processing', 'is_test' => $invoice->is_test, 'amount_cents' => $invoice->balanceCents(), 'currency' => 'AUD']);
            $this->invoices->audit($invoice, $actor, 'payment_started', ['payment' => $payment->uuid]);

            return [$payment, true];
        });
        if (! $created) {
            return $payment;
        }
        try {
            $result = $this->gateway->charge($payment, $data['nonce'] ?? '');
        } catch (\Throwable $error) {
            $result = ['status' => 'unknown', 'transaction' => null, 'message' => 'Payment outcome could not be verified. Please check payment status before trying again.'];
        }

        return $this->apply($payment, $result, $actor);
    }

    public function reconcile(StudioInvoice $invoice, ?User $actor = null): void
    {
        foreach ($invoice->payments()->whereIn('status', ['processing', 'unknown', 'submitted'])->get() as $payment) {
            // A fresh request may still be at the gateway. Never race it or release its reservation.
            if ($payment->status === 'processing' && $payment->created_at->gt(now()->subMinutes(2))) {
                continue;
            }
            try {
                $result = $this->gateway->reconcile($payment);
            } catch (\Throwable $error) {
                continue;
            }
            $this->apply($payment, $result, $actor);
        }
    }

    private function apply(StudioInvoicePayment $payment, array $result, ?User $actor): StudioInvoicePayment
    {
        return $this->invoices->locked($payment->invoice, function ($invoice) use ($payment, $result, $actor) {
            $payment = StudioInvoicePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (in_array($payment->status, ['succeeded', 'failed', 'reversed'], true)) {
                return $payment;
            }
            if ($payment->status === $result['status'] && $payment->provider_transaction_id === $result['transaction']) {
                return $payment;
            }
            $payment->update(['status' => $result['status'], 'provider_transaction_id' => $result['transaction'], 'message' => $result['message'], 'paid_at' => $result['status'] === 'succeeded' ? now() : null]);
            $this->invoices->audit($invoice, $actor, 'payment_'.$result['status'], ['payment' => $payment->uuid, 'amount_cents' => $payment->amount_cents]);
            if ($payment->status === 'succeeded') {
                app(StudioPaymentReceipts::class)->prepare($payment);
            }

            return $payment;
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['invoice' => $message]);
        }
    }
}
