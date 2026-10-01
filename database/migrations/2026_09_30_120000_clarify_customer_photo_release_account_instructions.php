<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $old = 'Sign in to the DancePro account you used to register for your studio gallery. If you have not added the studio yet, use the photo gallery access code provided by your studio. Contact your studio if you need the code.';
        $new = 'Your account already has access to your studio’s concert photo galleries. Sign in, open **My Media → My Events**, then choose **{{ studio_name }}** and the event you want to view. You do not need to enter a studio or gallery code.';
        $template = DB::table('concert_tracker_email_templates')->where('task_key', 'photo_release')->first();

        if ($template && str_contains($template->body, $old)) {
            DB::table('concert_tracker_email_templates')->where('id', $template->id)
                ->update(['body' => str_replace($old, $new, $template->body), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Retain the corrected wording and any subsequent administrator edits.
    }
};
