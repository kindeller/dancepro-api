<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('venue_address')->constrained('venues')->nullOnDelete();
        });

        DB::table('concert_booking_items')
            ->whereNotNull('scheduling_event_id')
            ->orderBy('id')
            ->eachById(function (object $item): void {
                $venueId = DB::table('scheduling_events')->where('id', $item->scheduling_event_id)->value('venue_id');
                DB::table('concert_booking_items')->where('id', $item->id)->update(['venue_id' => $venueId]);
            });
    }

    public function down(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });
    }
};
