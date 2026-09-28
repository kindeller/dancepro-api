<?php

namespace App\Features\StudioBilling\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertTracker;

class InvoiceTrackerWorkflow
{
    public function event(ConcertBooking $booking): ?SchedulingEvent
    {
        return $booking->items()->whereNull('cancelled_at')->whereNotNull('scheduling_event_id')
            ->with('schedulingEvent')->orderBy('event_date')->get()
            ->first(fn ($item) => ! $item->schedulingEvent?->cancelled_at)?->schedulingEvent;
    }

    public function workspace(ConcertBooking $booking, array $context = []): array
    {
        $event = $this->event($booking);
        $detail = $event ? app(ConcertTracker::class)->detail($event, 'invoice_sent') : [];

        return $detail + ['booking' => $booking, 'trackerBar' => $event ? app(BookingTrackerBar::class)->invoicePage($detail, $context) : null];
    }

    public function status(?ConcertBooking $booking, ?StudioInvoice $invoice): array
    {
        $sourceUrl = $invoice ? route('admin.studio-invoices.show', $invoice)
            : ($booking ? route('admin.concert-bookings.show', $booking).'#studio-invoice' : route('admin.studio-invoices.index'));
        $paid = $invoice?->status === 'issued' && $invoice->balanceCents() === 0 && ! $invoice->hasPendingPayment();
        $completedAt = $paid ? $invoice->payments->where('status', 'succeeded')->max('paid_at') : null;
        $label = $paid ? 'Paid' : ($invoice?->sent_at ? 'Not paid' : ($booking?->family_count_confirmed_at ? 'Not invoiced' : 'Pending'));
        $key = $paid ? 'complete' : ($label === 'Pending' ? 'waiting' : 'not_started');
        $detail = match ($label) {
            'Paid' => 'Invoice '.$invoice->number().' paid in full.',
            'Not paid' => 'Invoice '.$invoice->number().' · $'.number_format($invoice->balanceCents() / 100, 2).' outstanding.'.($invoice->hasPendingPayment() ? ' Online payment awaiting settlement.' : ''),
            'Pending' => 'Waiting for confirmed final family numbers. No invoice deadline yet.',
            default => $invoice?->status === 'issued' ? 'Available in the Studio Hub. Send the invoice email to start the payment deadline.' : 'Prepare and send the invoice seven days before the final event.',
        };
        $activity = $paid ? 'Paid '.$completedAt?->format('j M Y, g:i a')
            : ($invoice?->sent_at ? 'Sent '.$invoice->sent_at->format('j M Y, g:i a').' · '.($invoice->sentBy?->name ?? 'Former account') : null);

        return compact('key', 'label', 'detail', 'sourceUrl', 'completedAt', 'activity') + ['sourceLabel' => $invoice ? 'Open studio invoice' : 'Create invoice from booking'];
    }

    public function forEmail(SchedulingEvent $event): StudioInvoice
    {
        abort_unless($event->concertBookingItem?->concert_booking_id, 422, 'Link the event to a booking first.');
        $invoice = StudioInvoice::where('active_booking_id', $event->concertBookingItem->concert_booking_id)->first();
        abort_unless($invoice && $invoice->status === 'issued', 422, 'Prepare and send the invoice to the Studio Hub first.');
        abort_unless($invoice->balanceCents() > 0, 422, 'This invoice is already paid.');

        return $invoice;
    }

    public function deliveryCurrent(ConcertTrackerEmailDelivery $delivery): bool
    {
        $invoice = StudioInvoice::find($delivery->studio_invoice_id);

        return $invoice && $invoice->status === 'issued' && $invoice->active_booking_id
            && $invoice->concert_booking_id === $delivery->event?->concertBookingItem?->concert_booking_id
            && $invoice->balanceCents() > 0;
    }

    public function sent(ConcertTrackerEmailDelivery $delivery): void
    {
        if (! $delivery->studio_invoice_id) {
            return;
        }
        $invoice = StudioInvoice::whereKey($delivery->studio_invoice_id)->lockForUpdate()->firstOrFail();
        if ($invoice->sent_at) {
            return; // Resending a reminder must never restart the payment deadline.
        }
        $invoice->update(['sent_at' => $delivery->sent_at, 'sent_by_user_id' => $delivery->created_by_user_id,
            'due_date' => $delivery->sent_at->copy()->addDays(14)->toDateString()]);
        $invoice->events()->create(['user_id' => $delivery->created_by_user_id, 'action' => 'invoice_email_sent',
            'details' => ['delivery_id' => $delivery->id, 'due_date' => $invoice->due_date->toDateString()], 'created_at' => now()]);
    }
}
