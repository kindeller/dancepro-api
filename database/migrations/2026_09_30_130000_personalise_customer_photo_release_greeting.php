<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('concert_tracker_email_templates')->where('task_key', 'photo_release')->first();
        if ($template && str_starts_with($template->body, "Hi!\n")) {
            DB::table('concert_tracker_email_templates')->where('id', $template->id)
                ->update(['body' => 'Hi {{ first_name }},'.substr($template->body, 3), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Preserve the personalised greeting and any subsequent template edits.
    }
};
