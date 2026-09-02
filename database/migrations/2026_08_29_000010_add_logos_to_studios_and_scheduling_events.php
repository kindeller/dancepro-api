<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('cover_image_url');
        });

        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
