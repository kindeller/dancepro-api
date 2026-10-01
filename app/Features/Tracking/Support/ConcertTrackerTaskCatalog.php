<?php

namespace App\Features\Tracking\Support;

use Illuminate\Support\Collection;

class ConcertTrackerTaskCatalog
{
    public const GUARDED_EMAIL_TASKS = ['final_numbers_email', 'concert_week_email', 'post_concert_email', 'approval_link_email', 'director_corrections', 'invoice_sent'];

    public const PHASES = [
        'booking' => '1. Booking',
        'preparation' => '2. Preparation & assembly',
        'editing' => '3. Editing & approval',
        'completion' => '4. Completion',
    ];

    public function all(): Collection
    {
        $phaseCounts = [];

        return collect([
            $this->task('booking_received', 'booking', 'Booking & services', 'booking', 'source'),
            $this->task('final_numbers_email', 'booking', 'Seats & families email', 'booking', 'manual', 'booking_first', -30, 'final_numbers_email'),
            $this->task('final_numbers_received', 'booking', 'Seats & families numbers', 'booking', 'source'),
            $this->task('concert_week_email', 'booking', 'Concert week email', 'event', 'manual', 'event', -10, 'concert_week_email'),

            $this->task('crew_ready', 'preparation', 'Crew ready', 'event', 'source'),
            $this->task('programme_prepared', 'preparation', 'Prepare programme', 'event', 'source', 'event', -7),
            $this->task('video_titles', 'preparation', 'Video titles', 'event', 'manual'),
            $this->task('post_concert_email', 'preparation', 'Post-concert email', 'event', 'manual', 'event', 1, 'post_concert_email'),
            $this->task('slideshow_received', 'preparation', 'Studio videos & slideshows', 'event', 'manual', 'event', 2, 'slideshow_request_email'),

            $this->task('photos_uploaded', 'editing', 'Photo uploads', 'event', 'source'),
            $this->task('concert_edited', 'editing', 'Concert editing', 'event', 'manual'),
            $this->task('quality_check', 'editing', 'Final quality check', 'event', 'manual'),
            $this->task('video_uploaded', 'editing', 'Video uploads', 'event', 'source', 'event', 10),
            $this->task('approval_link_email', 'editing', 'Video approval link', 'event', 'manual', null, null, 'approval_link_email'),
            $this->task('director_corrections', 'editing', 'Director corrections', 'event', 'source', null, null, 'director_corrections'),

            $this->task('photo_release', 'completion', 'Photo release', 'event', 'source', 'booking_last', 2, 'photo_release'),
            $this->task('invoice_sent', 'completion', 'Invoice & payment', 'booking', 'source', 'booking_last', -7, 'invoice_sent'),
            $this->task('final_release', 'completion', 'Final video release', 'event', 'source'),
        ])->map(function (array $task, int $position) use (&$phaseCounts): array {
            $phaseNumber = array_search($task['phase'], array_keys(self::PHASES), true) + 1;
            $phaseCounts[$task['phase']] = ($phaseCounts[$task['phase']] ?? 0) + 1;
            $task['number'] = $phaseNumber.'.'.$phaseCounts[$task['phase']];
            $task['numbered_label'] = $task['number'].' '.$task['label'];
            $task['sort_order'] = $position;
            $task['description'] = $this->descriptions()[$task['key']];
            $task['instructions'] = $this->instructions()[$task['key']];
            $task['sourceLabel'] = match ($task['key']) {
                'booking_received' => 'Review booking & services',
                'final_numbers_received' => 'Record seats & families numbers',
                'invoice_sent', 'payment_received' => 'Open studio invoice',
                'crew_ready' => 'Manage event roster',
                'programme_prepared' => 'Prepare programme',
                'video_titles' => 'Prepare video titles',
                'photos_uploaded' => 'Open photo uploads',
                'photo_release' => 'Manage photo release',
                'video_uploaded', 'final_release' => 'Manage concert video',
                'director_approval' => 'Review director approval',
                'slideshow_received' => 'Review studio files',
                default => 'Open source',
            };
            $task['service'] = match ($task['key']) {
                'photos_uploaded', 'photo_release' => 'photo',
                'video_titles', 'slideshow_received', 'concert_edited', 'quality_check', 'video_uploaded',
                'video_holds', 'approval_link_email', 'director_corrections', 'director_approval', 'final_release' => 'video',
                default => null,
            };

            return $task;
        });
    }

    public function forPhase(string $phase): Collection
    {
        return $this->all()->when($phase !== 'all', fn (Collection $tasks) => $tasks->where('phase', $phase))->values();
    }

    public function find(string $key): array
    {
        // Keep the existing email record key for delivery history and scheduled jobs.
        // Confirmation is now a step inside 1.1, not a separate tracker column.
        if ($key === 'confirmation_information_email') {
            return array_replace($this->find('booking_received'), [
                'key' => $key, 'kind' => 'manual', 'templateKey' => $key,
                'description' => 'Review and send the booking confirmation after approval.',
            ]);
        }

        return $this->all()->firstWhere('key', $key) ?? abort(404);
    }

    private function instructions(): array
    {
        return [
            'booking_received' => [
                'Review the studio contact, services, event dates, venues and requirements. Save any changes before approval.',
                'Use Received when scheduling has not started; an optional acknowledgement email opens. Resolve venues, then choose Approved for all events or Partial Approval and select only the events you can confirm.',
                'Review and send the notification after approval. Cancellation requires a reason and opens an editable email. Status changes alone do not send emails; sent notifications remain in booking history and Studio messages.',
            ],
            'final_numbers_email' => [
                'Review the recipient and email preview, including the link to the Studio Hub.',
                'Send the email once. A successful send completes 1.2 and sets the 1.3 deadline to 10 days after sending.',
                'If no email is needed, choose Approved in the status box. This does not send a message or start the email-based deadline. Check delivery history before resending.',
            ],
            'final_numbers_received' => [
                'Count each family once across the booking, including performing siblings and parents. Quoted numbers are estimates until explicitly confirmed.',
                'Confirm the final family total, then open each concert and save the required crew seat allocations.',
                'Completion is automatic only when families are confirmed and seats are recorded for every concert. Each update records the staff member and date. DR portraits are not required.',
            ],
            'concert_week_email' => [
                'Check the event details and final requirements in the preview; events in the same booking within a 20-day window share one email, due ten days before the first event in that group, including dress rehearsals.',
                'Send once and check the delivery result. Successful delivery completes 1.4 for every event in the group; queued or scheduled messages are not yet complete.',
                'Use Approved only if the email is unnecessary. An additional copy requires explicit resend confirmation.',
            ],
            'crew_ready' => [
                'Use the embedded event availability sheet to assign the required roles for this event.',
                'Publish the roster and obtain the assigned crew acknowledgements. Resolve the outstanding availability requirements shown on the sheet.',
                'Readiness follows the same roster as full Event availability. The tracker assignee identifies task responsibility and does not assign a crew role.',
            ],
            'programme_prepared' => [
                'Use the studio-uploaded programme or upload/replace it as admin.',
                'Read the programme, check the extracted items, spelling and running order, then save and confirm the working programme.',
                'Review any changes before confirming again. Uploader work and video titles must use the current confirmed programme.',
            ],
            'video_titles' => [
                'Confirm the programme first, then review the item titles, subtitles, logo, colours and title-card previews.',
                'Use Video titles ready to publish the JPEG package and make it downloadable in the uploader. Saving the template or downloading a preview alone does not complete the task.',
                'If the programme or title content changes, review and publish a fresh package. Reopening the task withdraws the ready download.',
            ],
            'post_concert_email' => [
                'Review the post-concert message and studio recipient. One email covers the same 20-day booking group as 1.4; the standard deadline is the day after the final event in that group.',
                'Review every included event. Sending after the group has finished is recommended; you may send earlier after checking the timing warning and email wording. Successful delivery completes 2.4 for all included events; scheduling alone does not complete it.',
                'Choose Approved if the email is unnecessary; review the previous delivery before confirming any resend.',
            ],
            'slideshow_received' => [
                'Select the concert and review every current studio video, opening clip and slideshow. Admin can upload or replace files received separately.',
                'Approve each usable file or request a replacement. All current files must be approved for automatic completion.',
                'If files are held outside the portal, record receipt manually with a note. Choose Not required when no studio media is needed. Confirm their inclusion in the final exports in 3.4.',
            ],
            'photos_uploaded' => [
                'Check the current working programme and each photographer role. Queued and verified counts come from the laptop uploader.',
                'Each required photographer clicks Upload. The concert uploader records completion automatically after all photos are verified. All required roles must be complete.',
                'A waiver records missing work but does not itself complete an upload or release the gallery. Waiting stays blue until overdue; uploading is blue and complete is green.',
            ],
            'concert_edited' => [
                'Assign the editor in the tracker bar. Download the available programme, published titles and studio videos below.',
                'Edit the full concert consistently, in programme order, including all required titles and supplied studio media.',
                'Mark the status Complete when the edit is ready for the final quality check. This does not verify uploads, approve the concert or release it to customers.',
            ],
            'quality_check' => [
                'Review the full edit against all 20 checks for files, titles, video and audio.',
                'Tick checks as you go; each change saves automatically. Watch for the saved confirmation or any error before leaving.',
                'All checks are required. Complete all is available only to admin accounts. Unchecking a check reopens the task.',
            ],
            'video_uploaded' => [
                'Register and upload the final concert video queue from the laptop uploader. Wait for every file to be verified and confirm Finished Uploading.',
                'Complete editing and the quality checklist. Approve the current studio videos/slideshows and record which verified export includes each one.',
                'Resolve every readiness reason shown here. The standard deadline is 10 days after this event; the task completes automatically when the current concert is ready for review.',
            ],
            'approval_link_email' => [
                'Finish the current video uploads, editing, quality checks and studio-media inclusions first. Missing requirements appear above the email action.',
                'Review the director recipient and private approval link, then send once. Successful delivery completes this email task.',
                'The director reviews through their account. Sending the link does not release the video. Check delivery history before resending.',
            ],
            'director_corrections' => [
                'Read the director feedback and make the requested changes. Confirm Corrections complete when the edit is finished.',
                'Upload and verify the corrected concert, complete its readiness checks, then confirm Corrections uploaded.',
                'Both checks open the corrections email. Send it to complete the task. New feedback reopens the task; release without any feedback shows No corrections.',
            ],
            'photo_release' => [
                'Finish every required photographer upload. The target is 48 hours after the final booking event.',
                'Select ready events from this studio, or use Select all ready. Release selected events makes all their photo albums available together. Watermark and release name are under Advanced options.',
                'Review the customer, guest and studio messages, then send all release announcements once. Linked customers and gallery guests receive one announcement for the selected events; Studio Hub accounts receive family instructions and a code-free joining link. Studio Hub contacts with customer access also receive the standard gallery update. Sent or queued messages are skipped. Customers find each event under their studio.',
            ],
            'invoice_sent' => [
                'Approve and link the booking to its studio, and confirm final family numbers before preparing the video invoice.',
                'Review the draft, prices, discounts and notes. Send invoice saves it to the Studio Hub and opens the invoice email; send that email to start the 14-day payment deadline.',
                'Before sending, the standard deadline is seven days before the final event. Afterwards the task stays Not paid until settlement; review payment details or record a received bank transfer. Download the PDF from the invoice page.',
            ],
            'final_release' => [
                'Ensure the current video is ready for studio review and any requested corrections have been completed, uploaded and emailed.',
                'The studio owner/director uses Release concert video on their review page. This automatically approves and releases the current version.',
                'The studio then shares its video Access Code with families. Admin does not manually complete this task; the tracker follows the director release.',
            ],
        ];
    }

    private function descriptions(): array
    {
        return [
            'booking_received' => 'Review the booking and selected services, approve it, then send the booking confirmation from the same workflow. Approval status follows the unified booking record; email delivery is logged alongside it.',
            'confirmation_information_email' => 'Send the booking confirmation and initial information to the studio. Use the task email template; successful delivery completes this step.',
            'final_numbers_email' => 'Ask the studio for reserved seat numbers and final unique-family numbers, normally one month before the first concert. Successful sending completes this step and sets the numbers deadline to 10 days later; approve it manually when an email is unnecessary.',
            'final_numbers_received' => 'Confirm the total unique families and reserved crew seats for every concert in the booking. Completes automatically when all required fields are recorded. Not required for DR portraits.',
            'concert_week_email' => 'Send one concert-week email for the booking’s events in a 20-day group, ten days before its first event (including dress rehearsal). Successful delivery completes every included 1.4 cell.',
            'crew_ready' => 'Make sure every required role is filled, the roster is published and assigned crew have acknowledged it. Completes automatically from the event roster.',
            'programme_prepared' => 'Attach the programme, check its items and confirm the final working order by seven days before the first concert in its 20-day booking group (not dress rehearsal). Progress updates automatically from Programme preparation.',
            'photos_uploaded' => 'Track each photo role as Waiting, Uploading or Complete. The concert uploader automatically records completion for the current programme after verification; all required roles must be complete. Waivers stay separate.',
            'video_titles' => 'Prepare and check the concert video title cards using the approved programme. Video titles ready publishes the JPEG package for the uploader and completes this task.',
            'post_concert_email' => 'Send one post-concert email the day after the final event in its 20-day booking group. Explain Studio Hub uploads, photo access and video review/release; successful delivery completes every included 2.4 cell.',
            'slideshow_received' => 'Obtain and check any studio video, opening sequence or slideshow needed for the finished concert. Uploaded files complete this step automatically once all are approved. Open Review studio files to check them; use manual completion for files received outside the portal, or Not required when none are needed.',
            'concert_edited' => 'Complete the main concert edit, including the programme order, titles and supplied studio media. Mark complete when the edit is ready for final checking.',
            'quality_check' => 'Confirm all 20 checks for files, titles, video and audio. Each change saves automatically; every check is required for completion.',
            'video_uploaded' => 'Track the laptop video queue, verified files and Finished Uploading confirmation. Editing, quality checks and studio media inclusions must also be complete before the concert is ready for studio review.',
            'video_holds' => 'Record and resolve anything preventing delivery, such as missing footage, music, titles or replacement exports. Mark not required when there are no holds.',
            'approval_link_email' => 'Send the director the private video approval link and review instructions. Successful template delivery completes this step.',
            'director_corrections' => 'Review director feedback, confirm corrections complete and uploaded, then send the corrections email. Successful delivery completes the task; new feedback reopens it.',
            'director_approval' => 'Obtain and record the studio director’s approval of the final concert. Completes automatically when approval is recorded on the concert.',
            'photo_release' => 'Due 48 hours after the final booking event. Select completed events from this studio, release their photos together, then review and send the customer, guest and Studio Hub announcements together.',
            'invoice_sent' => 'Wait for confirmed final family numbers, then prepare and send the studio invoice seven days before the final event. Payment is due 14 days after the first invoice email is sent. Completes when the balance is paid.',
            'payment_received' => 'Completes automatically when the issued invoice balance is paid. Record received bank transfers on the invoice; pending card payments do not count until settled.',
            'final_release' => 'The studio director approves and releases the current concert video from their review page. Families use the video access code shared by the studio.',
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
