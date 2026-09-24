<?php

namespace App\Features\Tracking\Jobs;

use App\Features\Tracking\Mail\ConcertTrackerMessage;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
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
        if (! $delivery || ! in_array($delivery->status, ['queued', 'scheduled'], true)) {
            return;
        }
        if ($delivery->status === 'scheduled' && $delivery->scheduled_at?->isFuture()) {
            return;
        }

        try {
            $delivery->update(['status' => 'queued', 'queued_at' => $delivery->queued_at ?? now()]);
            Mail::to($delivery->recipient_email, $delivery->recipient_name)
                ->send(new ConcertTrackerMessage(
                    $delivery->subject,
                    $delivery->body,
                    route('concert-tracker.email-open', $delivery),
                    $delivery->event?->studio ? route('customer.studio-communications.index', $delivery->event->studio) : route('customer.media.index'),
                    $delivery->created_by_user_id,
                ));
            DB::transaction(function () use ($delivery): void {
                $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failure_message' => null]);
                $task = $delivery->task;
                $before = $task->only(['status', 'completed_by_user_id', 'completed_at']);
                $task->update([
                    'status' => 'complete',
                    'completed_by_user_id' => $delivery->created_by_user_id,
                    'completed_at' => now(),
                ]);
                DB::table('concert_tracker_task_changes')->insert([
                    'concert_tracker_task_id' => $task->id,
                    'user_id' => $delivery->created_by_user_id,
                    'action' => 'email_sent',
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($task->only(['status', 'completed_by_user_id', 'completed_at']), JSON_THROW_ON_ERROR),
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
