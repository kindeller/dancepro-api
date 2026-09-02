<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->index()->after('finishes_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });

        DB::table('concert_booking_items')->whereNotNull('scheduling_event_id')->update([
            'approval_status' => 'approved',
            'approved_at' => DB::raw('COALESCE(updated_at, NOW())'),
        ]);

        DB::table('scheduling_events')->whereNotNull('availability_deadline')->get(['id', 'availability_deadline'])
            ->each(function (object $event): void {
                DB::table('scheduling_events')->where('id', $event->id)->update([
                    'availability_deadline' => Carbon::parse($event->availability_deadline)->setTime(17, 0),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
