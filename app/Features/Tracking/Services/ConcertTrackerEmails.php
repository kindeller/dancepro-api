<?php

namespace App\Features\Tracking\Services;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\StudioBilling\Services\InvoiceTrackerWorkflow;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcertTrackerEmails
{
    public function __construct(
        private readonly ConcertTracker $tracker,
        private readonly ConcertTrackerTaskCatalog $catalog,
        private readonly ConcertEmailRehearsal $rehearsal,
        private readonly TrackerEmailContext $context,
    ) {}

    public function queue(
        SchedulingEvent $event,
        ConcertTrackerTask $task,
        ConcertTrackerEmailTemplate $template,
        ?User $actor,
        array $recipient,
    ): ConcertTrackerEmailDelivery {
        if ($event->cancelled_at) {
            throw ValidationException::withMessages(['email' => 'Emails cannot be scheduled for a cancelled event.']);
        }
        if (! $template->is_active || $template->task_key !== $this->catalog->find($task->task_key)['templateKey']) {
            throw ValidationException::withMessages(['recipient_email' => 'This template is not available for the selected task.']);
        }
        $workflowContext = in_array($task->task_key, ['approval_link_email', 'director_corrections'], true)
            ? app(ConcertDirectorReviews::class)->emailContext($event, $task->task_key) : null;
        abort_if($task->task_key === 'photo_release', 422, 'Use the registered-account release email page.');
        $invoice = $task->task_key === 'invoice_sent' ? app(InvoiceTrackerWorkflow::class)->forEmail($event) : null;
        abort_if($invoice && ($recipient['studio_invoice_uuid'] ?? null) !== $invoice->uuid, 409, 'The invoice changed. Reload before sending.');
        $rendered = $this->tracker->renderTemplate($template->subject, $template->body, $event);
        $scheduledAt = filled($recipient['scheduled_at'] ?? null) ? Carbon::parse($recipient['scheduled_at']) : null;
        $delivery = DB::transaction(function () use ($event, $task, $template, $actor, $recipient, $rendered, $scheduledAt, $workflowContext, $invoice): ConcertTrackerEmailDelivery {
            if (in_array($task->task_key, ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true)) {
                ConcertTrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
                $deliveries = $task->deliveries()->when($invoice, fn ($query) => $query->where('studio_invoice_id', $invoice->id));
                if ((clone $deliveries)->whereIn('status', ['queued', 'scheduled'])->exists()) {
                    throw ValidationException::withMessages(['email' => 'An email is already queued or scheduled for this task. Wait for delivery or cancel the scheduled message first.']);
                }
                $sent = (clone $deliveries)->where('status', 'sent')->reorder()->orderByDesc('id')->first();
                if ($sent && (! filter_var($recipient['confirm_resend'] ?? false, FILTER_VALIDATE_BOOLEAN) || (int) ($recipient['previous_delivery_id'] ?? 0) !== $sent->id)) {
                    throw ValidationException::withMessages(['email' => 'This email has already been sent. Review the latest delivery and confirm that you want to send another copy.']);
                }
            }

            return ConcertTrackerEmailDelivery::create([
                'scheduling_event_id' => $event->id,
                'context_hash' => $this->context->hash($event),
                'workflow_context' => $workflowContext,
                'studio_invoice_id' => $invoice?->id,
                'concert_tracker_task_id' => $task->id,
                'concert_tracker_email_template_id' => $template->id,
                'created_by_user_id' => $actor?->id,
                'recipient_name' => $recipient['recipient_name'] ?? null,
                'recipient_email' => $recipient['recipient_email'],
                'is_simulated' => $this->rehearsal->simulated(),
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'status' => $scheduledAt ? 'scheduled' : 'queued',
                'scheduled_at' => $scheduledAt,
                'queued_at' => $scheduledAt ? null : now(),
            ]);
        });
        $this->rehearsal->dispatch($delivery);

        return $delivery->refresh();
    }

    public function cancel(ConcertTrackerEmailDelivery $delivery, User $actor): void
    {
        if ($delivery->status !== 'scheduled') {
            throw ValidationException::withMessages(['delivery' => 'Only a scheduled email can be cancelled.']);
        }
        $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id]);
    }

    public function resend(ConcertTrackerEmailDelivery $original, User $actor): ConcertTrackerEmailDelivery
    {
        if ($original->task->task_key === 'photo_release') {
            throw ValidationException::withMessages(['delivery' => 'Use the photo release email page to retry outstanding recipients without duplicating sent announcements.']);
        }
        if (in_array($original->task->task_key, ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS, true)) {
            throw ValidationException::withMessages(['delivery' => 'Open the tracker task to review the current email and confirm a resend.']);
        }
        if ($original->invalidated_at || $original->event?->cancelled_at || ($original->context_hash && $original->context_hash !== $this->context->hash($original->event))) {
            throw ValidationException::withMessages(['delivery' => 'The booking has changed. Create a new message from the tracker task using the current details.']);
        }

        return DB::transaction(function () use ($original, $actor): ConcertTrackerEmailDelivery {
            $delivery = ConcertTrackerEmailDelivery::create([
                'scheduling_event_id' => $original->scheduling_event_id,
                'context_hash' => $original->context_hash,
                'concert_tracker_task_id' => $original->concert_tracker_task_id,
                'concert_tracker_email_template_id' => $original->concert_tracker_email_template_id,
                'created_by_user_id' => $actor->id,
                'recipient_name' => $original->recipient_name,
                'recipient_email' => $original->recipient_email,
                'is_simulated' => $this->rehearsal->simulated(),
                'subject' => $original->subject,
                'body' => $original->body,
                'status' => 'queued',
                'queued_at' => now(),
            ]);
            $this->rehearsal->dispatch($delivery);

            return $delivery->refresh();
        });
    }
}
