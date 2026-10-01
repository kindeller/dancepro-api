<?php

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\ConsolidateConcertEmailTasks;
use App\Features\Tracking\Services\ConcertEventGroups;
use App\Features\Tracking\Support\PostConcertEmailContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_tracker_automation_rules')->where('task_key', 'post_concert_email')->update(['offset_days' => 1, 'updated_at' => now()]);
        DB::table('concert_tracker_email_templates')->where('task_key', 'post_concert_email')->update(['subject' => PostConcertEmailContent::SUBJECT, 'body' => PostConcertEmailContent::BODY, 'updated_at' => now()]);
        $seen = [];
        foreach (SchedulingEvent::where('event_type', 'concert')->whereNull('cancelled_at')->with('concertBookingItem.booking.items.schedulingEvent')->lazyById(100) as $event) {
            $anchor = app(ConcertEventGroups::class)->anchorId($event);
            if (! isset($seen[$anchor])) {
                app(ConsolidateConcertEmailTasks::class)->execute($event, 'post_concert_email');
                $seen[$anchor] = true;
            }
        }
    }

    // Keep sent history grouped and the current wording on rollback to avoid duplicate emails.
    public function down(): void {}
};
