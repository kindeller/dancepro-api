<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_notifications', function (Blueprint $table): void {
            $table->timestamp('digest_notified_at')->nullable()->index()->after('read_at');
        });

        DB::table('crew_notifications')
            ->whereIn('type', ['shift_allocation', 'shift_changed', 'availability_request'])
            ->update(['digest_notified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('crew_notifications', function (Blueprint $table): void {
            $table->dropColumn('digest_notified_at');
        });
    }
};
