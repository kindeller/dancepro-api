<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table) {
            $table->string('portrait_photography_interest')->default('no')->after('wants_portrait_photography');
        });
    }

    public function down(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table) {
            $table->dropColumn('portrait_photography_interest');
        });
    }
};
