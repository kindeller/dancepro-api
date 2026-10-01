<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_tracker_tasks')->whereNull('assignee_user_id')
            ->whereNotNull('completed_at')->whereNotNull('completed_by_user_id')
            ->where(fn ($query) => $query->where('status', 'complete')->orWhere('status_override', 'complete'))
            ->update(['assignee_user_id' => DB::raw('completed_by_user_id')]);
    }

    public function down(): void
    {
        // Keep assignments: a later manual assignment cannot be distinguished safely.
    }
};
