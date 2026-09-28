<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->boolean('is_school')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->dropColumn('is_school');
        });
    }
};
