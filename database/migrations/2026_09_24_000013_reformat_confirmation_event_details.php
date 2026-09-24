<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const WITH_SHARED_SERVICES = "{{ event_details }}\n\nBooked services: {{ services }}";

    public function up(): void
    {
        $template = DB::table('concert_tracker_email_templates')->where('task_key', 'confirmation_information_email')->first();
        if ($template && str_contains($template->body, self::WITH_SHARED_SERVICES)) {
            DB::table('concert_tracker_email_templates')->where('id', $template->id)->update([
                'body' => str_replace(self::WITH_SHARED_SERVICES, '{{ event_details }}', $template->body),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $template = DB::table('concert_tracker_email_templates')->where('task_key', 'confirmation_information_email')->first();
        if ($template && ! str_contains($template->body, 'Booked services: {{ services }}')) {
            DB::table('concert_tracker_email_templates')->where('id', $template->id)->update([
                'body' => preg_replace('/\{\{ event_details \}\}/', self::WITH_SHARED_SERVICES, $template->body, 1),
                'updated_at' => now(),
            ]);
        }
    }
};
