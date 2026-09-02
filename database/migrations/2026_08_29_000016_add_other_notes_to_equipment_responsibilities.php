<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_equipment_responsibilities', function (Blueprint $table) {
            $table->string('other_notes')->nullable()->after('is_taking');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_equipment_responsibilities', function (Blueprint $table) {
            $table->dropColumn('other_notes');
        });
    }
};
