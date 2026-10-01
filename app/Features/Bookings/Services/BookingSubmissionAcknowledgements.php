<?php

namespace App\Features\Bookings\Services;

use App\Features\Bookings\Mail\BookingSubmitted;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingSubmissionAcknowledgements
{
    public function send(ConcertBooking $booking, ?User $account): void
    {
        $receipt = DB::transaction(function () use ($booking, $account): array {
            $locked = ConcertBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->submission_acknowledgement) {
                return $locked->submission_acknowledgement;
            }
            $studio = $locked->studio;
            $data = [
                'firstName' => $account?->emailGreetingName() ?: str($locked->contact_name)->before(' ')->toString(),
                'studioName' => $locked->studio_name,
                'items' => $locked->submission_snapshot['details']['items'],
                'hasStudio' => (bool) $studio,
                'accountUrl' => $studio ? route('customer.studio-communications.index', [$studio, 'tab' => 'events']) : route('login'),
            ];
            $receipt = [
                'status' => 'queued', 'queued_at' => now()->toIso8601String(),
                'recipient' => $account?->email ?: $locked->contact_email,
                'subject' => 'Booking received — '.$locked->studio_name,
                'body' => view('emails.bookings.submitted-text', $data)->render(),
                'content' => $data,
            ];
            if ($studio) {
                $message = StudioConversationMessage::create([
                    'studio_id' => $studio->id, 'direction' => 'from_dancepro', 'channel' => 'booking_receipt',
                    'sender_name' => 'DancePro', 'recipient_email' => $receipt['recipient'],
                    'subject' => $receipt['subject'], 'body' => $receipt['body'],
                    'sent_at' => now(), 'staff_read_at' => now(),
                ]);
                $receipt['studio_message_id'] = $message->id;
            }
            $locked->forceFill(['submission_acknowledgement' => $receipt])->save();

            return $receipt;
        });

        // A repeated form submission may retry a failed email, but never a sent one.
        if (! ConcertBooking::whereKey($booking->id)
            ->whereIn('submission_acknowledgement->status', ['queued', 'failed'])
            ->update(['submission_acknowledgement->status' => 'sending'])) {
            return;
        }
        try {
            Mail::to($receipt['recipient'])->send(new BookingSubmitted($receipt));
            ConcertBooking::whereKey($booking->id)->update([
                'submission_acknowledgement->status' => 'sent',
                'submission_acknowledgement->sent_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            ConcertBooking::whereKey($booking->id)->update(['submission_acknowledgement->status' => 'failed']);
            // The booking and its Studio Messages notification remain saved.
        }
    }
}
