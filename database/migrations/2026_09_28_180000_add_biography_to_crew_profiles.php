<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_profiles', function (Blueprint $table): void {
            $table->text('biography')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crew_profiles', function (Blueprint $table): void {
            $table->dropColumn('biography');
        });
    }
};
