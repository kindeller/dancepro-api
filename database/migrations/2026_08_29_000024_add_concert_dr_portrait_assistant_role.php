<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('crew_roles')->where('code', 'concert-dr-portrait-assistant')->exists()) {
            DB::table('crew_roles')->where('code', 'concert-dr-portrait-assistant')->update([
                'name' => 'Concert DR Portrait Assistant A',
                'event_type' => 'concert',
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('crew_roles')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'concert-dr-portrait-assistant',
            'name' => 'Concert DR Portrait Assistant A',
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
