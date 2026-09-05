<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table): void {
            $table->string('programme_path')->nullable();
            $table->string('programme_name')->nullable();
            $table->string('programme_mime')->nullable();
            $table->unsignedBigInteger('programme_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concert_booking_items', function (Blueprint $table): void {
            $table->dropColumn(['programme_path', 'programme_name', 'programme_mime', 'programme_size']);
        });
    }
};
