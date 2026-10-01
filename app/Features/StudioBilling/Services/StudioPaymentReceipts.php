<?php

namespace App\Features\StudioBilling\Services;

use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\StudioBilling\Jobs\SendStudioPaymentReceipt;
use App\Features\StudioBilling\Mail\StudioPaymentReceived;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\ConcertEmailRehearsal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StudioPaymentReceipts
{
    // Called within the invoice lock and payment transaction. Notifications and
    // receipt are stored atomically; email is dispatched only after commit.
    public function prepare(StudioInvoicePayment $payment): void
    {
        if ($payment->status !== 'succeeded' || $payment->receipt) {
            return;
        }
        $invoice = $payment->invoice->fresh();
        $recipient = mb_strtolower(trim($invoice->bill_to['email'] ?? ''));
        $account = User::whereRaw('lower(email) = ?', [$recipient])->where('is_active', true)->whereNotNull('email_verified_at')->first();
        if ($account && ! app(StudioCommunicationHistory::class)->canView($account, $invoice->studio)) {
            $account = null;
        }
        $firstName = $account?->emailGreetingName() ?: str($invoice->bill_to['contact'] ?? '')->before(' ')->toString();
        $balance = $invoice->balanceCents();
        $amount = '$'.number_format($payment->amount_cents / 100, 2).' AUD';
        $outstanding = '$'.number_format($balance / 100, 2).' AUD';
        $method = $payment->provider === 'manual' ? 'Recorded payment'.($payment->reference ? ' · '.$payment->reference : '') : ($payment->is_test ? 'Simulated card payment' : 'Card payment');
        $url = route('customer.studio-invoices.show', [$invoice->studio, $invoice]);
        $subject = ($payment->is_test ? 'Test payment received' : 'Payment received').' — Invoice '.$invoice->number();
        $body = 'Hi'.($firstName ? ' '.$firstName : '').",\n\n";
        $body .= $payment->is_test ? "This is a test payment acknowledgement. No real money was charged through DancePro.\n\n" : '';
        $body .= 'Thank you — we have received **'.$amount.'** for invoice **'.$invoice->number().'** for **'.$invoice->studio->name."**.\n\n";
        $body .= '**Payment date:** '.$payment->paid_at->format('j M Y')."\n\n**Payment method:** ".$method."\n\n";
        $body .= $balance === 0 ? "**Invoice paid in full. No further payment is required for this invoice.**\n\n" : '**Remaining balance: '.$outstanding.'.** You can view the balance and payment options in your account.'."\n\n";
        $body .= "Open **Studio Hub → Event invoices** to view your invoice, payment history and download a PDF.\n\nPayment does not change your media release status. Your studio will be notified separately when media is ready.\n\nIf this payment looks incorrect, please contact DancePro through Studio messages.\n\n[View invoice and payment history](".$url.')';
        $receipt = ['subject' => $subject, 'body' => $body, 'recipient' => $recipient, 'url' => $url,
            'paid_in_full' => $balance === 0, 'status' => 'queued', 'queued_at' => now()->toIso8601String()];
        $message = StudioConversationMessage::create(['studio_id' => $invoice->studio_id,
            'direction' => 'from_dancepro', 'channel' => 'payment_receipt', 'sender_name' => 'DancePro',
            'recipient_email' => $recipient, 'subject' => $subject, 'body' => $body,
            'sent_at' => now(), 'staff_read_at' => now()]);
        $receipt['studio_message_id'] = $message->id;
        $payment->forceFill(['receipt' => $receipt])->save();
        if (app(ConcertEmailRehearsal::class)->capturesLocally()) {
            DB::afterCommit(function () use ($payment): void {
                try {
                    SendStudioPaymentReceipt::dispatchSync($payment->id);
                } catch (\Throwable $exception) {
                    report($exception); // A mail failure must never undo or fail a recorded payment.
                }
            });
        } else {
            SendStudioPaymentReceipt::dispatch($payment->id)->afterCommit();
        }
    }

    public function send(int $paymentId): void
    {
        $receipt = DB::transaction(function () use ($paymentId) {
            $payment = StudioInvoicePayment::whereKey($paymentId)->lockForUpdate()->first();
            $receipt = $payment?->receipt;
            if (! $receipt || ! in_array($receipt['status'], ['queued', 'failed'], true)) {
                return null;
            }
            $receipt['status'] = $payment->status === 'succeeded' ? 'sending' : 'cancelled';
            $payment->forceFill(['receipt' => $receipt])->save();
            $this->updateAccountEmail($receipt, $receipt['status']);

            return $receipt['status'] === 'sending' ? $receipt : null;
        });
        if (! $receipt) {
            return;
        }
        try {
            Mail::to($receipt['recipient'])->send(new StudioPaymentReceived($receipt));
            $receipt['status'] = 'sent';
            $receipt['sent_at'] = now()->toIso8601String();
        } catch (\Throwable $exception) {
            $receipt['status'] = 'failed';
            $receipt['failed_at'] = now()->toIso8601String();
            StudioInvoicePayment::whereKey($paymentId)->update(['receipt' => $receipt]);
            $this->updateAccountEmail($receipt, 'failed');
            throw $exception;
        }
        StudioInvoicePayment::whereKey($paymentId)->update(['receipt' => $receipt]);
        $this->updateAccountEmail($receipt, 'sent');
    }

    private function updateAccountEmail(array $receipt, string $status): void
    {
        if ($email = CustomerAccountEmail::find($receipt['account_email_id'] ?? null)) {
            $email->update(['status' => $status, 'sent_at' => $status === 'sent' ? now() : null,
                'failed_at' => $status === 'failed' ? now() : null]);
        }
    }
}
