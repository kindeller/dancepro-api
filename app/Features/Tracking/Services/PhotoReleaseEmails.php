<?php

namespace App\Features\Tracking\Services;

use App\Features\Media\Models\ConcertPhotoReleaseGroup;
use App\Features\Publicity\Services\PublicityEventAccess;
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
        return app(PhotoReleaseAudience::class)->customers($group);
    }

    public function page(ConcertPhotoReleaseGroup $group): array
    {
        abort_unless($group->released_at, 422, 'Release the photo gallery before preparing its announcement.');
        $event = $this->event($group);

        $data = app(ConcertTracker::class)->detail($event, 'photo_release');
        $includedEvents = $group->collections()->with('concert')->get()->pluck('concert')->filter()->unique('id')->sortBy('event_date');
        $eventList = $includedEvents->map(fn ($concert) => '- '.$concert->photoGalleryTitle().' · '.$concert->event_date?->format('j M Y'))->implode("\n");
        if ($data['renderedTemplate']) {
            $data['renderedTemplate']['body'] .= "\n\n**Photos included in this release:**\n".$eventList;
        }

        return $data + [
            'includedEvents' => $includedEvents,
            'group' => $group, 'recipients' => $this->recipients($group),
            'audience' => app(PhotoReleaseAudience::class)->all($group),
            'guestMessage' => app(PhotoReleaseMessageContent::class)->guest($group, $eventList),
            'studioMessage' => app(PhotoReleaseMessageContent::class)->studio($group, $eventList),
            'studioJoinUrl' => app(PublicityEventAccess::class)->studioLink($group->studio),
            'deliveries' => ConcertTrackerEmailDelivery::with('createdBy')->where('photo_release_group_id', $group->id)->get(),
        ];
    }

    public function deliveryCurrent(ConcertTrackerEmailDelivery $delivery): bool
    {
        $group = ConcertPhotoReleaseGroup::find($delivery->photo_release_group_id);

        return $group && $group->released_at && $group->collections()->exists()
            && ! $group->collections()->where('status', '!=', 'published')->exists()
            && $group->studio?->status->value === 'active'
            && app(PhotoReleaseAudience::class)->current($group, $delivery);
    }

    public function send(ConcertPhotoReleaseGroup $group, User $actor, array $input = []): int
    {
        $deliveries = DB::transaction(function () use ($group, $actor, $input) {
            $group = ConcertPhotoReleaseGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $data = $this->page($group);
            $template = $data['template'];
            abort_unless($template && $template->is_active, 422, 'Activate the photo release email template first.');
            $rendered = $data['renderedTemplate'];
            $rendered['subject'] = $input['email_subject'] ?? $rendered['subject'];
            $rendered['body'] = $input['email_body'] ?? $rendered['body'];
            abort_if($data['audience']->contains('audience', 'studio') && ! $data['studioJoinUrl'], 422, 'Set an active studio access code before sending the family joining link.');
            $result = collect();
            foreach ($data['audience'] as $recipient) {
                $email = mb_strtolower($recipient->email);
                $delivery = ConcertTrackerEmailDelivery::firstOrNew(['photo_release_group_id' => $group->id, 'recipient_email' => $email,
                    'photo_release_mail_kind' => $recipient->audience === 'studio' ? 'studio' : 'gallery']);
                if ($delivery->exists && ! in_array($delivery->status, ['failed', 'cancelled'], true)) {
                    continue;
                }
                $message = $recipient->audience === 'studio' ? [
                    'subject' => $input['studio_email_subject'] ?? $data['studioMessage']['subject'],
                    'body' => $input['studio_email_body'] ?? $data['studioMessage']['body'],
                ] : $rendered;
                if ($recipient->audience === 'guest') {
                    $message = ['subject' => $input['guest_email_subject'] ?? $data['guestMessage']['subject'], 'body' => $input['guest_email_body'] ?? $data['guestMessage']['body']];
                    $message['body'] .= app(PhotoReleaseMessageContent::class)->guestFooter($group, $email);
                }
                if (in_array($recipient->audience, ['customer', 'studio'], true)) {
                    $message = app(PhotoReleaseMessageContent::class)->personalise($message, $recipient->first_name);
                }
                $delivery->fill(['scheduling_event_id' => $data['event']->id, 'concert_tracker_task_id' => $data['task']->id,
                    'concert_tracker_email_template_id' => $template->id, 'created_by_user_id' => $actor->id,
                    'recipient_name' => $recipient->name, 'subject' => $message['subject'], 'body' => $message['body'],
                    'workflow_context' => ['photo_release_audience' => $recipient->audience, 'recipient_user_id' => $recipient->user_id,
                        'studio_join_version' => $recipient->audience === 'studio' ? app(PublicityEventAccess::class)->studioVersion($group->studio) : null],
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
