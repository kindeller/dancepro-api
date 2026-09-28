<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Services\BookingInvoiceSource;
use App\Features\StudioBilling\Services\InvoiceTrackerWorkflow;
use App\Features\StudioBilling\Services\StudioInvoices;
use App\Features\Tracking\Services\BookingTrackerBar;

class BookingInvoiceWorkspace
{
    public function __construct(
        private readonly InvoiceTrackerWorkflow $workflow,
        private readonly BookingInvoiceSource $source,
        private readonly StudioInvoices $invoices,
        private readonly BookingTrackerBar $bar,
    ) {}

    public function page(ConcertBooking $booking, ?string $invoiceUuid, array $context = []): array
    {
        $history = StudioInvoice::where('concert_booking_id', $booking->id)->with('payments')->latest('id')->get();
        $invoice = $invoiceUuid ? $history->firstWhere('uuid', $invoiceUuid) : $history->firstWhere('active_booking_id', $booking->id);
        abort_if($invoiceUuid && ! $invoice, 404);
        $workspace = $this->workflow->workspace($booking, $context);

        return [
            'invoice' => $invoice?->load('studio', 'booking', 'payments', 'events.user', 'sentBy'),
            'invoiceHistory' => $history,
            'issueBlockers' => $invoice?->status === 'draft' ? $this->invoices->issueBlockers($invoice) : [],
            'stale' => $invoice && $invoice->source_hash !== $this->source->hash($booking),
            'trackerBar' => $workspace['trackerBar'] ?? $this->bar->pendingPage($booking, 'invoice_sent', $context),
        ];
    }
}
