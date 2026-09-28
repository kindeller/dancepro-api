<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_tracker_tasks', function (Blueprint $table): void {
            $table->string('status_override', 30)->nullable();
            $table->string('follow_up_status', 30)->default('none');
        });
    }

    public function down(): void
    {
        Schema::table('concert_tracker_tasks', function (Blueprint $table): void {
            $table->dropColumn(['status_override', 'follow_up_status']);
        });
    }
};
