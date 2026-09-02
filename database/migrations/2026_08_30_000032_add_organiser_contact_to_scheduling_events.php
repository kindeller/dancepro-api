<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->string('organiser_name')->nullable()->after('name');
            $table->string('organiser_phone', 50)->nullable()->after('organiser_name');
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->dropColumn(['organiser_name', 'organiser_phone']);
        });
    }
};
