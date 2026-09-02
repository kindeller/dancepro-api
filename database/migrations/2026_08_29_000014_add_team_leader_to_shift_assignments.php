<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_shift_assignments', function (Blueprint $table) {
            $table->boolean('is_team_leader')->default(false)->index()->after('crew_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_shift_assignments', function (Blueprint $table) {
            $table->dropColumn('is_team_leader');
        });
    }
};
