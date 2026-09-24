<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'booking_open_information' => ['Concert booking information', 'DancePro concert bookings', $this->bookingInformation(), false],
            'booking_request_received' => ['Booking request received', 'Thanks for your booking request', $this->bookingReceived(), false],
            'confirmation_information_email' => ['Booking confirmation and event information', 'Please confirm your DancePro booking details', $this->confirmation(), true],
            'final_numbers_email' => ['Final numbers and technical information', 'Your upcoming concert - final information needed', $this->finalNumbers(), true],
            'concert_week_email' => ['Concert Week requirements', "It's concert time - {{ event_name }}", $this->concertWeek(), true],
            'post_concert_email' => ['Post-concert information', 'Your concert photos and what happens next', $this->postConcert(), true],
            'approval_link_email' => ['Video approval', 'Your concert video is ready for approval', $this->videoApproval(), true],
        ];
        foreach ($templates as $taskKey => [$name, $subject, $body, $active]) {
            DB::table('concert_tracker_email_templates')->updateOrInsert(['task_key' => $taskKey], [
                'uuid' => DB::table('concert_tracker_email_templates')->where('task_key', $taskKey)->value('uuid') ?? (string) Str::uuid(),
                'name' => $name, 'subject' => $subject, 'body' => $body, 'is_active' => $active,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}

    private function bookingInformation(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

DancePro concert bookings are open. We provide concert photography, dress rehearsal portraits and professionally produced concert video, delivered through secure online galleries.

Photography has no upfront studio cost and includes full-resolution digital products for families. Videography is an all-in studio service so every participating family can view and download the finished concert.

Visit https://www.dancepro.com.au/book for current services, pricing, requirements and booking availability.

Kind regards,
DancePro Photography & Video
TEXT;
    }

    private function bookingReceived(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

Thanks for completing our booking form. We have received your request and are compiling the schedule. We will contact you if we have any questions or conflicts.

Please tell us if any concert dates, times or venues change. The current booking information is available at https://www.dancepro.com.au/book.

We look forward to working with you.

Kind regards,
DancePro Photography & Video
TEXT;
    }

    private function confirmation(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

Thank you for booking DancePro Photography & Video. Please reply to confirm the following details are correct:

{{ event_details }}

{{#if video}}VIDEO DELIVERY
Your studio's concert video is an all-in service for participating families. Our current estimate is based on {{ family_count }} families. After editing, you or a trusted staff member will receive a private approval link before the video is released to families.

Videography requires 5-6 adjacent seats at the rear of the auditorium, power and an XLR feed from the sound desk. We normally require venue access before curtain for setup.{{/if}}

{{#if photo}}PHOTOGRAPHY
Photography requires 1-2 audience seats around eye level with the stage, normally 6-10 rows back. Please check that heads, lights and speakers will not obstruct the view.
{{#if second_photographer}}A second photographer is booked. Please also reserve one front-corner seat for the alternate angle.{{/if}}
{{ gallery_access }}{{/if}}

{{#if portraits}}DRESS REHEARSAL PORTRAITS
Please provide approximately 8 x 10 metres of space with power, a desk and a chair. Dancers should be brought to the portrait area by a teacher or minder.{{/if}}

PROGRAMME AND STUDIO MEDIA
Please provide the final programme before the concert. Any studio slideshow or video required in the finished concert should be supplied within two days after the event.

We will contact you again closer to the concert. Please let us know immediately if a date, time, venue or service changes.

Kind regards,
DancePro Photography & Video
TEXT;
    }

    private function finalNumbers(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

We are looking forward to your upcoming concert. Please confirm the details below and send anything still outstanding.

{{ event_details }}

1. Send the final programme with correct spelling.
2. Confirm the final number of participating families.
3. Confirm the seats reserved for the DancePro team.
4. Confirm venue access from {{ arrival_time }} for a {{ start_time }} start.
{{#if video}}5. Confirm power and an XLR sound feed are available at the video position.
6. Send any slideshow or studio video within two days after the concert.{{/if}}

Crew currently assigned: {{ crew_names }}.

{{#if second_photographer}}Two photographers are booked. Please reserve the normal photography position and an additional front-corner seat.{{/if}}
{{#if portraits}}For dress rehearsal portraits, please provide approximately 8 x 10 metres of clear space, power, a desk and a chair.{{/if}}
{{#if programme_missing}}Our system still shows the programme as missing or unconfirmed. Please send the final copy as soon as possible.{{/if}}

Please reply with the final details or any changes.

Kind regards,
DancePro Photography & Video
TEXT;
    }

    private function concertWeek(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

We are looking forward to {{ event_name }} this week.

{{ event_details }}

DancePro crew: {{ crew_names }}
Planned arrival: {{ arrival_time }}

{{#if programme_missing}}Please send the final programme at your earliest convenience. Check that spelling and running order are correct.{{/if}}
Please confirm final family numbers and the reserved seat rows and numbers for our team.
{{#if video}}Please confirm access to power and an XLR sound feed at the video position. If the concert includes a slideshow or presentation, send it within two days after the event.{{/if}}

Please remind the audience that photography and video recording are not permitted during the performance.

Thanks,
DancePro Photography & Video
TEXT;
    }

    private function postConcert(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

Congratulations on {{ event_name }} and thank you for having DancePro there.

{{#if photo}}Your concert photographs are being prepared for the studio gallery. {{ gallery_access }} Please share the released gallery information with dancers and families when it becomes available.{{/if}}

{{#if video}}We are editing the concert video and will email you when it is ready for approval.{{/if}}{{#if programme_missing}}

We still need the final programme. Please send a PDF with the correct titles and running order as soon as possible.{{/if}}{{#if slideshow_missing}}

If the finished concert requires a studio slideshow or video presentation, please send it now. If it is not received before editing finishes, production may continue without it.{{/if}}

{{#if photo}}Studio principals can use the current principal offer shown in Gallery settings for eligible full-resolution images. Please avoid sharing large batches publicly during the initial gallery sales period.{{/if}}

Kind regards,
DancePro Photography & Video
TEXT;
    }

    private function videoApproval(): string
    {
        return <<<'TEXT'
Hi {{ contact_name }},

Your concert video for {{ event_name }} is ready for approval.

Please watch the complete concert and check that:
1. Every routine is included and plays in full.
2. Titles and running order match the final programme.
3. Supplied studio videos and slideshows are present where expected.
4. Picture and sound are correct throughout.

Please complete the review within five days. If you find an error, do not release the concert; send the corrections through the approval page. Once everything is correct, use the Approve button on the video page.

After approval, DancePro will make the final concert available for your families. Please do not distribute access before approval is complete.

{{#if photo}}Your concert photo gallery is also available through DancePro. {{ gallery_access }}{{/if}}

Kind regards,
DancePro Photography & Video
TEXT;
    }
};
