<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('crew_roles')->where('code', 'concert-photographer-p2')->exists()) {
            DB::table('crew_roles')->where('code', 'concert-photographer-p2')->update([
                'name' => 'Concert Photographer P2',
                'event_type' => 'concert',
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('crew_roles')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'concert-photographer-p2',
            'name' => 'Concert Photographer P2',
            'event_type' => 'concert',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Retain the role to avoid deleting qualifications or historical assignments.
    }
};
