<?php

namespace App\Features\StudioBilling\Services;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Bookings\Services\ConcertBookingQuote;
use Illuminate\Validation\ValidationException;

class BookingInvoiceSource
{
    public function __construct(private readonly ConcertBookingQuote $quotes) {}

    public function data(ConcertBooking $booking): array
    {
        $booking->load('items');
        $details = $booking->only(['wants_portrait_photography', 'wants_concert_photography', 'wants_concert_videography', 'final_family_count', 'family_count_confirmed_at']);
        $items = $booking->items->whereNull('cancelled_at')->values();
        $details['items'] = $items->map(fn ($item) => ['item_type' => $item->item_type->value, 'title' => $item->title, 'event_date' => $item->event_date->toDateString()])->all();

        return ['details' => $details, 'quote' => $this->quotes->calculate($details), 'bill_to' => ['name' => $booking->studio_name, 'contact' => $booking->contact_name, 'email' => $booking->contact_email, 'address' => $booking->postal_address, 'phone' => $booking->contact_phone, 'events' => $items->map(fn ($item) => ['title' => $item->title, 'date' => $item->event_date->format('j M Y')])->all()], 'due_date' => $items->max('event_date')?->copy()->addDays(7)->toDateString() ?? today()->addDays(7)->toDateString()];
    }

    public function hash(ConcertBooking $booking): string
    {
        return hash('sha256', json_encode($this->data($booking), JSON_THROW_ON_ERROR));
    }

    public function videoLine(ConcertBooking $booking): array
    {
        $data = $this->data($booking);
        $quote = $data['quote'];
        if (! $booking->family_count_confirmed_at || ! $quote['video_quote_cents']) {
            throw ValidationException::withMessages(['invoice' => 'Confirm final family numbers before creating the video invoice.']);
        }

        $familyRate = $quote['families'] * ConcertBookingQuote::VIDEO_FAMILY_RATE_CENTS
            > $quote['video_concerts'] * ConcertBookingQuote::VIDEO_CONCERT_RATE_CENTS;
        $concerts = $quote['video_concerts'].' '.($quote['video_concerts'] === 1 ? 'concert' : 'concerts');

        return [
            'description' => $familyRate
                ? "Concert Video - Online Delivery - Family Rate ({$concerts})"
                : 'Concert Video - Online Delivery - Flat Rate (per concert)',
            'quantity' => $familyRate ? $quote['families'] : $quote['video_concerts'],
            'unit_cents' => $familyRate ? ConcertBookingQuote::VIDEO_FAMILY_RATE_CENTS : ConcertBookingQuote::VIDEO_CONCERT_RATE_CENTS,
        ];
    }
}
