<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_tracker_tasks', function (Blueprint $table): void {
            $table->json('quality_checks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concert_tracker_tasks', function (Blueprint $table): void {
            $table->dropColumn('quality_checks');
        });
    }
};
