<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concerts', function (Blueprint $table): void {
            $table->foreignId('concert_booking_item_id')->nullable()->unique()
                ->constrained('concert_booking_items')->nullOnDelete();
            $table->string('booking_programme_path_synced')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concerts', function (Blueprint $table): void {
            $table->dropForeign(['concert_booking_item_id']);
            $table->dropUnique(['concert_booking_item_id']);
            $table->dropColumn(['concert_booking_item_id', 'booking_programme_path_synced']);
        });
    }
};
