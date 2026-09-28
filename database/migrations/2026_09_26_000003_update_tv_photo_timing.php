<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_slideshows', fn (Blueprint $table) => $table->unsignedSmallInteger('photo_seconds')->default(2)->change());
        // Migrate the previous default; keep deliberately customised timings.
        DB::table('tv_slideshows')->where('photo_seconds', 8)->update(['photo_seconds' => 2]);
    }

    public function down(): void
    {
        Schema::table('tv_slideshows', fn (Blueprint $table) => $table->unsignedSmallInteger('photo_seconds')->default(8)->change());
        // Existing choices are retained when rolling back the schema default.
    }
};
