<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Owner-confirmed policy: approved studio membership includes concert photos.
        // Pending/unverified associations are deliberately excluded.
        DB::table('customer_studios')->whereNotNull('membership_confirmed_at')->whereNull('access_verified_at')
            ->update(['access_verified_at' => DB::raw('membership_confirmed_at')]);
    }

    public function down(): void
    {
        // Preserve access already granted; rollback must not revoke customer access.
    }
};
