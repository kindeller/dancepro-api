<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('type', 'staff')
            ->whereIn('id', DB::table('crew_profiles')->select('user_id'))
            ->update(['type' => 'crew', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Crew accounts must not regain administrator access during rollback.
    }
};
