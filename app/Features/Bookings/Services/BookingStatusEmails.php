<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Mail\BookingStatusMessage;
use App\Features\Bookings\Models\BookingStatusNotification;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingStatusEmails
{
    public function hash(ConcertBooking $booking): string
    {
        return hash('sha256', json_encode([
            'status' => $booking->status->value,
            'studio_id' => $booking->studio_id,
            'details' => app(BookingChangeSnapshot::class)->forBooking($booking),
            'approvals' => $booking->items->sortBy('id')->pluck('approval_status', 'uuid'),
        ], JSON_THROW_ON_ERROR));
    }

    public function draft(ConcertBooking $booking, User $staff, ?string $reason): BookingStatusNotification
    {
        return DB::transaction(function () use ($booking, $staff, $reason): BookingStatusNotification {
            $booking = ConcertBooking::lockForUpdate()->findOrFail($booking->id);

            return $this->buildDraft($booking, $staff, $reason);
        });
    }

    private function buildDraft(ConcertBooking $booking, User $staff, ?string $reason): BookingStatusNotification
    {
        $hash = $this->hash($booking);
        $existing = $booking->statusNotifications()->where('context_hash', $hash)->where('booking_status', $booking->status)->latest('id')->first();
        if ($existing) {
            return $existing;
        }
        $greeting = User::where('email', $booking->contact_email)->first()?->emailGreetingName()
            ?: str($booking->contact_name)->before(' ')->toString();
        $intro = match ($booking->status->value) {
            'received' => "Thanks for getting your booking in early! We've received your request for {$booking->studio_name}. We haven't started scheduling these events yet, so your dates aren't confirmed just yet. We'll be in touch when we're ready to complete scheduling and confirm your booking.\n\nThere's nothing else you need to do for now. If any of your plans change, just let us know.",
            'partial_approval' => "We've approved the events marked Approved below for {$booking->studio_name}. The remaining events are still pending and are not confirmed. We'll be in touch about those separately. Please contact us if you'd like to discuss any of the details.",
            'cancelled' => "Your booking for {$booking->studio_name} has been cancelled.\n\nReason: {$reason}\n\nThe events listed below will not proceed under this booking. If this is unexpected or you have any questions, please contact us through Studio messages.",
        };
        $events = $booking->items->map(fn ($item) => '- '.($item->title ?: ($item->item_type->value === 'concert' ? 'Concert' : 'DR Portrait')).' · '.$item->event_date->format('j M Y').' · '.($item->approval_status === 'pending' ? 'Pending approval' : ucfirst($item->approval_status)))->implode("\n");

        return $booking->statusNotifications()->create([
            'created_by_user_id' => $staff->id, 'booking_status' => $booking->status->value,
            'reason' => $reason, 'context_hash' => $hash, 'recipient_email' => $booking->contact_email,
            'subject' => $booking->status->label().' — '.$booking->studio_name,
            'body' => "Hi {$greeting},\n\n{$intro}\n\n{$events}\n\nYou can find your booking and messages in your DancePro account.",
        ]);
    }

    public function refreshDraft(ConcertBooking $booking, BookingStatusNotification $notification, User $staff): BookingStatusNotification
    {
        abort_unless($notification->concert_booking_id === $booking->id, 404);
        if (! in_array($booking->status->value, ['received', 'partial_approval', 'cancelled'], true)) {
            throw ValidationException::withMessages(['email' => 'Use the current booking status workflow to prepare a notification.']);
        }

        return $this->draft($booking, $staff, $booking->statusNotifications()->where('booking_status', 'cancelled')->first()?->reason);
    }

    public function page(ConcertBooking $booking, BookingStatusNotification $notification, array $context): array
    {
        abort_unless($notification->concert_booking_id === $booking->id, 404);

        return [
            'booking' => $booking, 'notification' => $notification->load('sender'),
            'trackerBar' => app(BookingTrackerBar::class)->page($booking, $context),
            'current' => hash_equals($notification->context_hash, $this->hash($booking)),
        ];
    }

    public function send(ConcertBooking $booking, BookingStatusNotification $notification, User $staff, array $input): void
    {
        abort_unless($notification->concert_booking_id === $booking->id, 404);
        $claimed = DB::transaction(function () use ($booking, $notification, $staff, $input): BookingStatusNotification {
            $booking = ConcertBooking::lockForUpdate()->findOrFail($booking->id);
            $notification = BookingStatusNotification::lockForUpdate()->findOrFail($notification->id);
            if (! in_array($notification->delivery_status, ['draft', 'failed'], true)) {
                throw ValidationException::withMessages(['email' => 'This notification has already been sent or is being sent. Check the delivery status before trying again.']);
            }
            if (! hash_equals($notification->context_hash, $this->hash($booking))) {
                throw ValidationException::withMessages(['email' => 'Booking details or status have changed. Return to the booking and prepare an updated notification.']);
            }
            $notification->update($input + ['delivery_status' => 'sending', 'sent_by_user_id' => $staff->id]);

            return $notification;
        });
        try {
            Mail::to($claimed->recipient_email)->send(new BookingStatusMessage($claimed));
        } catch (\Throwable $exception) {
            report($exception);
            $claimed->update(['delivery_status' => 'failed']);
            throw ValidationException::withMessages(['email' => 'The email could not be sent. The booking status is saved; please try sending again.']);
        }
        DB::transaction(function () use ($claimed, $booking, $staff): void {
            $message = $booking->studio_id ? StudioConversationMessage::create([
                'studio_id' => $booking->studio_id, 'sender_user_id' => $staff->id,
                'direction' => 'from_dancepro', 'channel' => 'booking_status',
                'sender_name' => $staff->name, 'recipient_email' => $claimed->recipient_email,
                'subject' => $claimed->subject, 'body' => $claimed->body,
                'sent_at' => now(), 'staff_read_at' => now(),
            ]) : null;
            $claimed->update(['delivery_status' => 'sent', 'sent_at' => now(), 'studio_message_id' => $message?->id]);
        });
    }
}
