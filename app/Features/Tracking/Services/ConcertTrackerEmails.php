<?php

namespace App\Features\Tracking\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerEmailTemplate;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcertTrackerEmails
{
    public function __construct(private readonly ConcertTracker $tracker) {}

    public function queue(
        SchedulingEvent $event,
        ConcertTrackerTask $task,
        ConcertTrackerEmailTemplate $template,
        ?User $actor,
        array $recipient,
    ): ConcertTrackerEmailDelivery {
        if (! $template->is_active || $template->task_key !== $task->task_key) {
            throw ValidationException::withMessages(['recipient_email' => 'This template is not available for the selected task.']);
        }
        $rendered = $this->tracker->renderTemplate($template->subject, $template->body, $event);
        $scheduledAt = filled($recipient['scheduled_at'] ?? null) ? Carbon::parse($recipient['scheduled_at']) : null;
        $delivery = ConcertTrackerEmailDelivery::create([
            'scheduling_event_id' => $event->id,
            'concert_tracker_task_id' => $task->id,
            'concert_tracker_email_template_id' => $template->id,
            'created_by_user_id' => $actor?->id,
            'recipient_name' => $recipient['recipient_name'] ?? null,
            'recipient_email' => $recipient['recipient_email'],
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => $scheduledAt ? 'scheduled' : 'queued',
            'scheduled_at' => $scheduledAt,
            'queued_at' => $scheduledAt ? null : now(),
        ]);
        $pending = SendConcertTrackerEmail::dispatch($delivery->id);
        if ($scheduledAt) {
            $pending->delay($scheduledAt);
        }

        return $delivery;
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
        return DB::transaction(function () use ($original, $actor): ConcertTrackerEmailDelivery {
            $delivery = ConcertTrackerEmailDelivery::create([
                'scheduling_event_id' => $original->scheduling_event_id,
                'concert_tracker_task_id' => $original->concert_tracker_task_id,
                'concert_tracker_email_template_id' => $original->concert_tracker_email_template_id,
                'created_by_user_id' => $actor->id,
                'recipient_name' => $original->recipient_name,
                'recipient_email' => $original->recipient_email,
                'subject' => $original->subject,
                'body' => $original->body,
                'status' => 'queued',
                'queued_at' => now(),
            ]);
            SendConcertTrackerEmail::dispatch($delivery->id);

            return $delivery;
        });
    }
}
