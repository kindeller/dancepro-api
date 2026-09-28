<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table): void {
            $table->unsignedInteger('final_family_count')->nullable();
            $table->timestamp('family_count_confirmed_at')->nullable();
            $table->foreignId('family_count_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table): void {
            $table->dropForeign(['family_count_confirmed_by']);
            $table->dropColumn(['final_family_count', 'family_count_confirmed_at', 'family_count_confirmed_by']);
        });
    }
};
