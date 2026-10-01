<?php

use App\Features\Tracking\Support\ConcertWeekEmailContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_tracker_email_templates')->where('task_key', 'concert_week_email')->update([
            'body' => ConcertWeekEmailContent::BODY,
            'updated_at' => now(),
        ]);
    }

    // Content refresh only; retain accurate instructions when rolling back.
    public function down(): void {}
};
