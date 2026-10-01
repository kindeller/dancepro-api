<?php

namespace App\Features\Tracking\Support;

class ConcertWeekEmailContent
{
    public const SUBJECT = 'Your upcoming events - {{ studio_name }}';

    public const BODY = <<<'TEXT'
Hi {{ contact_name }},

We are looking forward to your upcoming events! Here are the details for each event in this group, including our crew, arrival/setup times and reserved seats.

{{ event_details }}

## Check your Concert events
Select **Open Concert events** below and sign in to your DancePro account. In **Studio Hub → Concert events**, open each event and look under **Your actions**. Please follow the due dates shown for each action.

If you have not already done so, confirm your event details, final family numbers and reserved seats. If a date, venue or arrival/setup, start or finish time is incorrect, select **Request changes** in that event's Booking details as soon as possible.

## Programme
Please upload all programmes for these concerts by **{{ programme_due_date }}**, seven days before the first concert in this group. Dress rehearsals do not bring this deadline forward.

For each concert, open **Upload programme**, choose the final programme and select **Upload programme**. PDF is preferred; other formats are accepted. Check spelling, item titles and the running order before uploading.

If the correct final version is already uploaded, you do not need to upload it again. If it changes, use **Replace programme** in the same event. Keep the programme attached to the concert it belongs to, including separate junior and senior concerts.

{{#if video}}## Videos & slideshows
For each concert that includes supplied clips, open **Upload videos & slideshows** under **Your actions**. Select every opening video, slideshow or presentation to be included in your concert video, then select **Upload studio files**. You can select up to 20 files at a time and return to add more later.

Check **Submitted files** in that event to see what has uploaded and any DancePro review notes. Follow the upload due date shown. If you uploaded the wrong version or need help, let us know through **Studio messages**.

Please also ensure access to power and an XLR sound feed at the videography position.{{/if}}

Please remind the audience that photography and video recording are not permitted during the performance.

For any questions, send us a message through **Studio messages** in your Studio Hub. We look forward to seeing you!
TEXT;
}
