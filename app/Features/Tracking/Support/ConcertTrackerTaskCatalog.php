<?php

namespace App\Features\Tracking\Support;

use Illuminate\Support\Collection;

class ConcertTrackerTaskCatalog
{
    public const PHASES = [
        'booking' => 'Booking',
        'preparation' => 'Preparation & assembly',
        'editing' => 'Editing & approval',
        'completion' => 'Completion',
    ];

    public function all(): Collection
    {
        return collect([
            $this->task('booking_received', 'booking', 'Booking & services', 'booking', 'source'),
            $this->task('confirmation_information_email', 'booking', 'Confirmation information email', 'booking', 'manual', null, null, 'confirmation_information_email'),
            $this->task('final_numbers_email', 'booking', 'Final numbers & technical email', 'booking', 'manual', 'booking_first', -30, 'final_numbers_email'),
            $this->task('final_numbers_received', 'booking', 'Final numbers received', 'booking', 'manual'),
            $this->task('concert_week_email', 'booking', 'Concert Week email', 'event', 'manual', 'event', -7, 'concert_week_email'),

            $this->task('crew_ready', 'preparation', 'Crew ready', 'event', 'source'),
            $this->task('programme_prepared', 'preparation', 'Programme', 'event', 'source', 'event', -7),
            $this->task('photos_uploaded', 'preparation', 'Photo uploads', 'event', 'source'),
            $this->task('post_concert_email', 'preparation', 'Post-concert email', 'event', 'manual', 'event', 1, 'post_concert_email'),
            $this->task('slideshow_received', 'preparation', 'Studio video/slideshow', 'event', 'manual', 'event', 2, 'slideshow_request_email'),

            $this->task('concert_edited', 'editing', 'Concert edited', 'event', 'manual'),
            $this->task('quality_check', 'editing', 'Final quality check', 'event', 'manual'),
            $this->task('video_uploaded', 'editing', 'Video uploaded', 'event', 'source'),
            $this->task('video_holds', 'editing', 'Video holds/missing media', 'event', 'manual'),
            $this->task('approval_link_email', 'editing', 'Approval link sent', 'event', 'manual', null, null, 'approval_link_email'),
            $this->task('director_corrections', 'editing', 'Director corrections', 'event', 'manual'),
            $this->task('director_approval', 'editing', 'Director approval', 'event', 'source'),

            $this->task('photo_release', 'completion', 'Photo gallery', 'event', 'source'),
            $this->task('invoice_sent', 'completion', 'Invoice', 'booking', 'manual', 'booking_last', 7, 'invoice_sent'),
            $this->task('payment_received', 'completion', 'Payment received', 'booking', 'manual'),
            $this->task('final_release', 'completion', 'Final video release', 'event', 'source'),
        ])->map(function (array $task, int $position): array {
            $task['sort_order'] = $position;
            $task['description'] = $this->descriptions()[$task['key']];

            return $task;
        });
    }

    public function forPhase(string $phase): Collection
    {
        return $this->all()->when($phase !== 'all', fn (Collection $tasks) => $tasks->where('phase', $phase))->values();
    }

    public function find(string $key): array
    {
        return $this->all()->firstWhere('key', $key) ?? abort(404);
    }

    private function descriptions(): array
    {
        return [
            'booking_received' => 'Check that the booking is approved and the required photo and video services are selected. Complete automatically from the unified booking record.',
            'confirmation_information_email' => 'Send the booking confirmation and initial information to the studio. Use the task email template; successful delivery completes this step.',
            'final_numbers_email' => 'Ask the studio for final family numbers and technical information, normally one month before the first concert. Successful template delivery completes this step.',
            'final_numbers_received' => 'Confirm that the studio has supplied its final numbers and technical details. Review the response, record any note, then mark this task complete.',
            'concert_week_email' => 'Send the final Concert Week requirements for this event, normally seven days before it. Successful template delivery completes this step.',
            'crew_ready' => 'Make sure every required role is filled, the roster is published and assigned crew have acknowledged it. Completes automatically from the event roster.',
            'programme_prepared' => 'Attach the programme, check its items and confirm the final working order by seven days before the event. Progress updates automatically from Programme preparation.',
            'photos_uploaded' => 'Ensure required photographer contributions are closed or waived and all expected photo files are verified. Completes automatically from the uploader gallery.',
            'post_concert_email' => 'Send the studio its post-concert information and explain what happens next. Successful template delivery completes this step.',
            'slideshow_received' => 'Obtain and check any studio video, opening sequence or slideshow needed for the finished concert. Mark complete when the usable files are received.',
            'concert_edited' => 'Complete the main concert edit, including the programme order, titles and supplied studio media. Mark complete when the edit is ready for final checking.',
            'quality_check' => 'Watch the completed concert and check picture, sound, titles, order and exported files. Record issues in notes or mark complete when it passes.',
            'video_uploaded' => 'Upload the finished concert video and make sure an available video asset is attached to the concert. Completes automatically when uploaded media is present.',
            'video_holds' => 'Record and resolve anything preventing delivery, such as missing footage, music, titles or replacement exports. Mark not required when there are no holds.',
            'approval_link_email' => 'Send the director the private video approval link and review instructions. Successful template delivery completes this step.',
            'director_corrections' => 'Apply every correction requested by the studio director and upload the revised version. Mark not required if approval needs no changes.',
            'director_approval' => 'Obtain and record the studio director’s approval of the final concert. Completes automatically when approval is recorded on the concert.',
            'photo_release' => 'Prepare the customer albums, choose the parent cover and release the complete photo gallery. Progress updates automatically from the gallery and release group.',
            'invoice_sent' => 'Finalise the studio invoice and send it after the last concert. Successful delivery of the invoice template completes this shared booking task.',
            'payment_received' => 'Confirm the studio invoice has been paid and record any follow-up needed. Mark complete when payment has cleared.',
            'final_release' => 'Publish the approved concert video for customer access. Completes automatically when the final concert record is published.',
        ];
    }

    private function task(
        string $key,
        string $phase,
        string $label,
        string $scope,
        string $kind,
        ?string $dueBasis = null,
        ?int $dueOffsetDays = null,
        ?string $templateKey = null,
        bool $optional = false,
    ): array {
        return compact('key', 'phase', 'label', 'scope', 'kind', 'dueBasis', 'dueOffsetDays', 'templateKey', 'optional');
    }
}
