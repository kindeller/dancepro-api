<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', fn (Blueprint $table) => $table->text('gallery_code_encrypted')->nullable());
        Schema::table('concert_code_groups', fn (Blueprint $table) => $table->text('code_encrypted')->nullable());
    }

    public function down(): void
    {
        Schema::table('studios', fn (Blueprint $table) => $table->dropColumn('gallery_code_encrypted'));
        Schema::table('concert_code_groups', fn (Blueprint $table) => $table->dropColumn('code_encrypted'));
    }
};
