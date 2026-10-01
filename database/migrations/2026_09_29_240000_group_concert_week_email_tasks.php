<?php

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Actions\ConsolidateConcertWeekTasks;
use App\Features\Tracking\Services\ConcertEventGroups;
use App\Features\Tracking\Support\ConcertWeekEmailContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_tracker_automation_rules')->where('task_key', 'concert_week_email')->update(['offset_days' => -10, 'updated_at' => now()]);
        DB::table('concert_tracker_email_templates')->where('task_key', 'concert_week_email')->update(['subject' => ConcertWeekEmailContent::SUBJECT, 'body' => ConcertWeekEmailContent::BODY, 'updated_at' => now()]);
        $seen = [];
        foreach (SchedulingEvent::where('event_type', 'concert')->whereNull('cancelled_at')->with('concertBookingItem.booking.items.schedulingEvent')->lazyById(100) as $event) {
            $anchor = app(ConcertEventGroups::class)->anchorId($event);
            if (! isset($seen[$anchor])) {
                app(ConsolidateConcertWeekTasks::class)->execute($event);
                $seen[$anchor] = true;
            }
        }
    }

    // Data consolidation is retained: splitting sent history would reintroduce duplicate emails.
    public function down(): void {}
};
