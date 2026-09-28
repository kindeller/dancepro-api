<?php

namespace App\Features\Tracking\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PhotoReleaseEmails
{
    public function event(ConcertPhotoReleaseGroup $group)
    {
        $event = $group->collections()->with('concert.bookingItem.schedulingEvent')->get()
            ->map(fn ($album) => $album->concert?->bookingItem?->schedulingEvent)->filter()->sortBy('event_date')->last();
        abort_unless($event && $event->studio_id === $group->studio_id && ! $event->cancelled_at, 404);

        return $event;
    }

    public function recipients(ConcertPhotoReleaseGroup $group)
    {
        return User::where('is_active', true)->whereNotNull('email_verified_at')
            ->whereIn('id', CustomerStudio::where('studio_id', $group->studio_id)->whereNotNull('access_verified_at')->select('user_id'))
            ->get()->filter(fn ($user) => $user->canAccessCustomer())->unique(fn ($user) => mb_strtolower($user->email));
    }

    public function page(ConcertPhotoReleaseGroup $group): array
    {
        abort_unless($group->released_at, 422, 'Release the photo gallery before preparing its announcement.');
        $event = $this->event($group);

        return app(ConcertTracker::class)->detail($event, 'photo_release') + [
            'group' => $group, 'recipients' => $this->recipients($group),
            'deliveries' => ConcertTrackerEmailDelivery::with('createdBy')->where('photo_release_group_id', $group->id)->get(),
        ];
    }

    public function deliveryCurrent(ConcertTrackerEmailDelivery $delivery): bool
    {
        $group = ConcertPhotoReleaseGroup::find($delivery->photo_release_group_id);

        return $group && $group->released_at && $group->collections()->exists()
            && ! $group->collections()->where('status', '!=', 'published')->exists()
            && $this->recipients($group)->contains(fn ($user) => strcasecmp($user->email, $delivery->recipient_email) === 0);
    }

    public function send(ConcertPhotoReleaseGroup $group, User $actor): int
    {
        $deliveries = DB::transaction(function () use ($group, $actor) {
            $group = ConcertPhotoReleaseGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $data = $this->page($group);
            $template = $data['template'];
            abort_unless($template && $template->is_active, 422, 'Activate the photo release email template first.');
            $rendered = app(ConcertTracker::class)->renderTemplate($template->subject, $template->body, $data['event']);
            $result = collect();
            foreach ($data['recipients'] as $recipient) {
                $email = mb_strtolower($recipient->email);
                $delivery = ConcertTrackerEmailDelivery::firstOrNew(['photo_release_group_id' => $group->id, 'recipient_email' => $email]);
                if ($delivery->exists && ! in_array($delivery->status, ['failed', 'cancelled'], true)) {
                    continue;
                }
                $delivery->fill(['scheduling_event_id' => $data['event']->id, 'concert_tracker_task_id' => $data['task']->id,
                    'concert_tracker_email_template_id' => $template->id, 'created_by_user_id' => $actor->id,
                    'recipient_name' => $recipient->name, 'subject' => $rendered['subject'], 'body' => $rendered['body'],
                    'status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'failure_message' => null, 'cancelled_at' => null,
                    'is_simulated' => app(ConcertEmailRehearsal::class)->simulated()])->save();
                $result->push($delivery);
            }

            return $result;
        });
        foreach ($deliveries as $delivery) {
            app(ConcertEmailRehearsal::class)->dispatch($delivery);
        }

        return $deliveries->count();
    }
}
