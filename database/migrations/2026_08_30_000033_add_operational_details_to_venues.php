<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->text('operational_notes')->nullable()->after('parking_notes');
            $table->string('reference_image_path')->nullable()->after('map_path');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['operational_notes', 'reference_image_path']);
        });
    }
};
