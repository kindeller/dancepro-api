<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'approval_link_email' => ['Video approval link', '{{ event_name }} is ready for review', <<<'BODY'
Hi {{ contact_name }},

Your concert video for **{{ event_name }}** is ready to review.

[REVIEW CONCERT VIDEO]({{ review_url }})

Sign in to your studio owner/director account, then watch every item. Please check the running order, titles, picture, sound and supplied studio videos or slideshows.

If anything needs changing, add the video filename, time and correction on the review page. We will let you know when the updated videos are ready.

When you are happy with the complete concert, click **Release concert video**. This approves and makes it available to families who enter the video access code. The code appears in your Studio Hub after release, ready for you to share with your dancers and families.
BODY],
            'director_corrections' => ['Concert corrections ready', 'Please review the updated {{ event_name }} concert video', <<<'BODY'
Hi {{ contact_name }},

Thank you for your feedback. The corrections to **{{ event_name }}** have been made and the updated videos have been uploaded.

[REVIEW UPDATED CONCERT]({{ review_url }})

Please check the concert again. If you need any further changes, add them on the review page. When everything is correct, click **Release concert video** and share the video access code from your Studio Hub with your dancers and families.
BODY],
            'photo_release' => ['Photo gallery release', 'Your {{ studio_name }} concert photos are ready', <<<'BODY'
Hi!

**Congratulations on your performance! We are excited to share your images with you. The new {{ studio_name }} concert photo gallery is now available.**

[VIEW GALLERY]({{ gallery_url }})

Sign in to the DancePro account you used to register for your studio gallery. If you have not added the studio yet, use the photo gallery access code provided by your studio. Contact your studio if you need the code.

Choose your favourite full-resolution digital images for printing, sharing and keeping. Your available albums, including any previously released concerts, are together in your studio gallery.

{{#if portraits}}Remember to explore the dress rehearsal portrait albums when available.{{/if}}

Current prices and any applicable offers are shown in your gallery and at checkout. After your order is ready, your downloads are available in **My Media**. We recommend using a computer when downloading a full collection.

{{#if video}}Your studio will contact you separately with the concert video access code after the director has reviewed and released the video.{{/if}}

For help, visit the Help section in your DancePro account. You can also follow DancePro on [Facebook](https://www.facebook.com/DanceProPhotoVideo) and [Instagram](https://www.instagram.com/danceprophoto).

Thank you for choosing DancePro Photography & Video.
BODY],
        ];
        foreach ($templates as $key => [$name, $subject, $body]) {
            DB::table('concert_tracker_email_templates')->updateOrInsert(['task_key' => $key], [
                'uuid' => DB::table('concert_tracker_email_templates')->where('task_key', $key)->value('uuid') ?? (string) Str::uuid(), 'name' => $name, 'subject' => $subject, 'body' => $body,
                'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Retain editable templates and delivery history on rollback.
    }
};
