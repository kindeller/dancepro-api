<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_shifts', function (Blueprint $table) {
            $table->string('period')->nullable()->change();
        });

        $concertEventIds = DB::table('scheduling_events')->where('event_type', 'concert')->pluck('id');
        DB::table('scheduling_shifts')->whereIn('scheduling_event_id', $concertEventIds)->update(['period' => null]);

        DB::table('concert_booking_items')->whereNotNull('scheduling_event_id')->orderBy('id')->eachById(function (object $item): void {
            $studioName = DB::table('concert_bookings')->where('id', $item->concert_booking_id)->value('studio_name');
            if ($studioName) {
                DB::table('scheduling_events')->where('id', $item->scheduling_event_id)->update(['name' => $studioName]);
            }
        });
    }

    public function down(): void
    {
        DB::table('scheduling_shifts')->whereNull('period')->update(['period' => 'afternoon']);
        Schema::table('scheduling_shifts', function (Blueprint $table) {
            $table->string('period')->nullable(false)->change();
        });
    }
};
