<?php

namespace App\Features\Tracking\Support;

class PostConcertEmailContent
{
    public const SUBJECT = 'Thank you, {{ studio_name }} - your photos, videos and next steps';

    public const BODY = <<<'TEXT'
Hi {{ contact_name }},

Congratulations on your performances, and thank you for having DancePro capture them! This update covers:

{{ event_summary }}

## Follow your events in Studio Hub
Select **Open Concert events** below and sign in to your DancePro account. In **Studio Hub → Concert events**, open each event to see **Your actions** and **DancePro progress**. Please follow the deadlines shown for any outstanding actions.

## Programmes
If a final concert programme is still outstanding, open **Upload programme** under that concert's **Your actions**. Upload the correct titles and running order; PDF is preferred. If the final copy is already there, there is no need to upload it again. Use **Replace programme** if it has changed, and let us know through **Studio messages**. Dress rehearsals do not need a programme.

{{#if video}}## Studio videos & slideshows
Please ensure every opening video, slideshow or supplied clip needed in the finished concert video is uploaded to its own event. Open **Upload videos & slideshows**, choose the files and select **Upload studio files**. Check **Submitted files** and any DancePro review notes. You do not need to upload the same files again. If there are no supplied clips, or you need to replace a file, let us know through **Studio messages**.
{{/if}}

{{#if photos}}## Concert photos
Follow **Photo gallery released** under **DancePro progress**. Once released, it shows the gallery link and **Access Code**. Share these with your dancers and families so they can sign in or create a DancePro account and add your studio in **My Profile → My Studio** using the studio code.

Their studio access also gives them access to its released concert photo galleries, where they can view and order images. Purchased photos are available in **My Media → My Purchases**. Registered accounts will be notified when the gallery release email is sent; this message does not mean the photos have already been released.
{{/if}}

{{#if video}}## Concert video review & release
DancePro will prepare your concert videos and email you when they are ready for review. Until then, **Review & release concert video** in **Your actions** remains unavailable.

When it becomes available, your studio owner can watch the videos, submit any corrections and release the concert when satisfied. If corrections are needed, we will notify you when the updated videos are ready to check again.

After release, **Concert video released** under **DancePro progress** shows the link and **Access Code**. Please send these to your dancers and families. They can sign in, open **My Media → My Events → Add media → Stream & download Concert videos**, and enter the code to add the released concert to their account. They can then stream it or download individual videos, or select multiple videos to download.

Families cannot access the concert video until it has been released and they have its access code. The studio photo code does not unlock concert videos.
{{/if}}

If you have questions or need to tell us about a change, open **Studio messages** in your Studio Hub and send us a message.

Thank you again for choosing DancePro!
TEXT;
}
