<?php

namespace App\Features\StudioBilling\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\StudioBilling\Models\StudioBillingSetting;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioInvoices
{
    public function __construct(private readonly BookingInvoiceSource $source) {}

    public function create(ConcertBooking $booking, User $actor, bool $test, string $kind): StudioInvoice
    {
        return DB::transaction(function () use ($booking, $actor, $test, $kind) {
            $booking = ConcertBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->check($booking->studio_id && $booking->status->value === 'approved', 'Approve and link the booking to a studio first.');
            $existing = StudioInvoice::where('active_booking_id', $booking->id)->first();
            if ($existing) {
                return $existing;
            }
            $data = $this->source->data($booking);
            $line = $kind === 'video' ? $this->source->videoLine($booking) : ['description' => 'Studio services', 'quantity' => 1, 'unit_cents' => 0];
            $invoice = StudioInvoice::create(['studio_id' => $booking->studio_id, 'concert_booking_id' => $booking->id, 'active_booking_id' => $booking->id, 'created_by_user_id' => $actor->id, 'status' => 'draft', 'is_test' => $test, 'bill_to' => $data['bill_to'], 'lines' => [$line], 'total_cents' => $line['unit_cents'], 'gst_cents' => (int) round($line['unit_cents'] / 11), 'currency' => 'AUD', 'source_hash' => $this->source->hash($booking), 'due_date' => today()->addDays(14)]);
            $this->audit($invoice, $actor, 'created', ['kind' => $kind]);

            return $invoice;
        });
    }

    public function save(StudioInvoice $invoice, User $actor, array $data): void
    {
        $this->locked($invoice, function ($invoice) use ($actor, $data) {
            $this->check($invoice->status === 'draft', 'Only draft invoices can be edited.');
            $lines = array_map(fn ($line) => ['description' => $line['description'], 'quantity' => (int) $line['quantity'], 'unit_cents' => self::cents($line['unit_amount'])], array_values($data['lines']));
            $discount = self::cents($data['discount_amount'] ?? '0');
            if ($discount > 0) {
                $lines[] = ['description' => 'Discount', 'quantity' => 1, 'unit_cents' => -$discount, 'is_discount' => true];
            }
            $total = array_sum(array_map(fn ($line) => $line['quantity'] * $line['unit_cents'], $lines));
            $this->check($total > 0 && $total <= 100000000, 'Invoice total must be between $0.01 and $1,000,000.');
            $invoice->update(['lines' => $lines, 'total_cents' => $total, 'gst_cents' => (int) round($total / 11), 'due_date' => $data['due_date'] ?? $invoice->due_date, 'notes' => $data['notes'] ?? null]);
            $this->audit($invoice, $actor, 'draft_updated');
        });
    }

    public function refresh(StudioInvoice $invoice, User $actor): void
    {
        $this->locked($invoice, function ($invoice) use ($actor) {
            $this->check($invoice->status === 'draft', 'Only draft invoices can be refreshed.');
            $line = $this->source->videoLine($invoice->booking);
            $data = $this->source->data($invoice->booking);
            $invoice->update(['lines' => [$line], 'total_cents' => $line['unit_cents'], 'gst_cents' => (int) round($line['unit_cents'] / 11), 'bill_to' => $data['bill_to'], 'source_hash' => $this->source->hash($invoice->booking)]);
            $this->audit($invoice, $actor, 'quote_refreshed');
        });
    }

    public function issue(StudioInvoice $invoice, User $actor): void
    {
        $this->locked($invoice, function ($invoice) use ($actor) {
            $this->check($invoice->status === 'draft', 'This invoice is no longer a draft.');
            $booking = ConcertBooking::whereKey($invoice->concert_booking_id)->lockForUpdate()->firstOrFail();
            $invoice->setRelation('booking', $booking);
            $blockers = $this->issueBlockers($invoice);
            $this->check(! $blockers, implode(' ', $blockers));
            $issuer = StudioBillingSetting::first()->only(['business_name', 'abn', 'address', 'email', 'bank_name', 'bank_bsb', 'bank_account']);
            $invoice->update(['status' => 'issued', 'issuer' => $issuer, 'issued_at' => now(), 'due_date' => today()->addDays(14)]);
            $this->audit($invoice, $actor, 'issued');
        });
    }

    public function issueBlockers(StudioInvoice $invoice): array
    {
        $issuer = StudioBillingSetting::first();

        return array_values(array_filter([
            $invoice->status !== 'draft' ? 'This invoice is no longer a draft.' : null,
            $invoice->booking->status->value !== 'approved' ? 'The booking must still be approved.' : null,
            $invoice->source_hash !== $this->source->hash($invoice->booking) ? 'The booking has changed. Refresh the quote or void this draft and create a new invoice.' : null,
            ! $issuer?->business_name || ! $issuer?->abn || ! $issuer?->email ? 'Set all invoice business details before issuing.' : null,
            $invoice->total_cents <= 0 ? 'Add a positive invoice amount before issuing.' : null,
        ]));
    }

    public function void(StudioInvoice $invoice, User $actor, string $reason): void
    {
        $this->locked($invoice, function ($invoice) use ($actor, $reason) {
            $this->check($invoice->status !== 'void' && $invoice->paidCents() === 0 && ! $invoice->hasPendingPayment(), 'Paid or pending invoices cannot be voided.');
            $invoice->update(['status' => 'void', 'active_booking_id' => null, 'voided_at' => now()]);
            $this->audit($invoice, $actor, 'voided', ['reason' => $reason]);
        });
    }

    public function recordPayment(StudioInvoice $invoice, User $actor, array $data): void
    {
        $this->locked($invoice, function ($invoice) use ($actor, $data) {
            $prior = StudioInvoicePayment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($prior) {
                $this->check($prior->studio_invoice_id === $invoice->id && $prior->provider === 'manual', 'This payment reference has already been used.');

                return;
            }
            $amount = self::cents($data['amount']);
            $this->check($invoice->status === 'issued' && ! $invoice->hasPendingPayment(), 'Issue the invoice and resolve any pending online payment first.');
            $this->check($amount > 0 && $amount <= $invoice->balanceCents(), 'Payment must not exceed the outstanding balance.');
            $payment = $invoice->payments()->create(['idempotency_key' => $data['idempotency_key'], 'user_id' => $actor->id, 'provider' => 'manual', 'status' => 'succeeded', 'is_test' => $invoice->is_test, 'amount_cents' => $amount, 'currency' => 'AUD', 'reference' => $data['reference'], 'paid_at' => $data['paid_at']]);
            $this->audit($invoice, $actor, 'payment_recorded', ['payment' => $payment->uuid, 'amount_cents' => $amount, 'reference' => $data['reference']]);
        });
    }

    public function reverse(StudioInvoice $invoice, StudioInvoicePayment $payment, User $actor, string $reason): void
    {
        $this->locked($invoice, function ($invoice) use ($payment, $actor, $reason) {
            $payment = StudioInvoicePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->check($payment->studio_invoice_id === $invoice->id && $payment->provider === 'manual' && $payment->status === 'succeeded', 'Only a recorded manual payment can be reversed here.');
            $this->check(! $invoice->hasPendingPayment(), 'Resolve pending payments first.');
            $payment->update(['status' => 'reversed', 'reversed_at' => now()]);
            $this->audit($invoice, $actor, 'manual_payment_reversed', ['payment' => $payment->uuid, 'reason' => $reason]);
        });
    }

    public function saveSettings(array $details): void
    {
        $settings = StudioBillingSetting::first() ?? new StudioBillingSetting;
        $settings->fill($details)->save();
    }

    public function audit(StudioInvoice $invoice, ?User $actor, string $action, array $details = []): void
    {
        $invoice->events()->create(['user_id' => $actor?->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }

    public function locked(StudioInvoice $invoice, callable $action): mixed
    {
        return DB::transaction(fn () => $action(StudioInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail()));
    }

    public static function cents(string $amount): int
    {
        [$whole,$fraction] = array_pad(explode('.', $amount), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['invoice' => $message]);
        }
    }
}
