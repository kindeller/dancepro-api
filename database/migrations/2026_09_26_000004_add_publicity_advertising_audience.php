<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicity_slides', function (Blueprint $table): void {
            // Existing standard advertising belongs to the competition TV pilot.
            $table->string('audience', 16)->default('competition');
        });
    }

    public function down(): void
    {
        Schema::table('publicity_slides', function (Blueprint $table): void {
            $table->dropColumn('audience');
        });
    }
};
