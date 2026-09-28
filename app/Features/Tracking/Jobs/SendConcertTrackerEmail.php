<?php

namespace App\Features\Tracking\Jobs;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Services\InvoiceTrackerWorkflow;
use App\Features\Tracking\Actions\SetSeatsFamiliesDeadline;
use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Services\ConcertEmailRehearsal;
use App\Features\Tracking\Services\PhotoReleaseEmails;
use App\Features\Tracking\Services\TrackerEmailContext;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendConcertTrackerEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = ConcertTrackerEmailDelivery::with(['task', 'event.studio'])->find($this->deliveryId);
        if (! $delivery || ! in_array($delivery->status, ['queued', 'scheduled', 'failed'], true)) {
            return;
        }
        if ($delivery->status === 'scheduled' && $delivery->scheduled_at?->isFuture()) {
            return;
        }

        if ($delivery->event?->cancelled_at) {
            $delivery->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return;
        }
        if ($delivery->context_hash && $delivery->context_hash !== app(TrackerEmailContext::class)->hash($delivery->event)) {
            $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'invalidated_at' => now(), 'cancellation_reason' => 'Booking details changed after this message was prepared. Review and schedule a new message.']);

            return;
        }
        if (in_array($delivery->task->task_key, ['approval_link_email', 'director_corrections'], true) && ! app(ConcertDirectorReviews::class)->deliveryCurrent($delivery)) {
            $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'The video upload or director feedback changed. Review the current email.']);

            return;
        }
        if ($delivery->task->task_key === 'photo_release' && ! app(PhotoReleaseEmails::class)->deliveryCurrent($delivery)) {
            $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'The gallery release or registered account access changed.']);

            return;
        }
        if ($delivery->task->task_key === 'invoice_sent' && ! app(InvoiceTrackerWorkflow::class)->deliveryCurrent($delivery)) {
            $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'This invoice was voided, replaced or paid.']);

            return;
        }
        $simulated = app(ConcertEmailRehearsal::class)->simulated();
        if ($delivery->is_simulated && ! $simulated) {
            throw new \RuntimeException('A simulated email cannot be replayed through a live mail transport.');
        }

        try {
            $delivery->update(['is_simulated' => $simulated, 'status' => 'queued', 'queued_at' => $delivery->queued_at ?? now()]);
            Mail::to($delivery->recipient_email, $delivery->recipient_name)
                ->send(new ConcertTrackerMessage(
                    $delivery->subject,
                    $delivery->body,
                    route('concert-tracker.email-open', $delivery),
                    $delivery->studio_invoice_id ? route('customer.studio-invoices.show', [$delivery->event->studio, StudioInvoice::findOrFail($delivery->studio_invoice_id)]) : ($delivery->event?->studio ? route($delivery->photo_release_group_id ? 'customer.profile.studio' : 'customer.studio-communications.index', $delivery->event->studio) : route('customer.media.index')),
                    $delivery->created_by_user_id,
                ));
            DB::transaction(function () use ($delivery): void {
                $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failure_message' => null, 'failed_at' => null]);
                app(SetSeatsFamiliesDeadline::class)->execute($delivery);
                app(InvoiceTrackerWorkflow::class)->sent($delivery);
                $task = $delivery->task;
                // A request for studio files is not evidence that those files were received.
                if (in_array($task->task_key, ['slideshow_received', 'invoice_sent', 'payment_received', 'photo_release'], true)) {
                    return;
                }
                $before = $task->only(['status', 'status_override', 'completed_by_user_id', 'completed_at']);
                $task->update([
                    'status' => 'complete',
                    'status_override' => in_array($task->task_key, ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true) ? null : $task->status_override,
                    'completed_by_user_id' => $delivery->created_by_user_id,
                    'completed_at' => now(),
                ]);
                DB::table('concert_tracker_task_changes')->insert([
                    'concert_tracker_task_id' => $task->id,
                    'user_id' => $delivery->created_by_user_id,
                    'action' => 'email_sent',
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($task->only(['status', 'status_override', 'completed_by_user_id', 'completed_at']), JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            throw $exception;
        }
    }
}
