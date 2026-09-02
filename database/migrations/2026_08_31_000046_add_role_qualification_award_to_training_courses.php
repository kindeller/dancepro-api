<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_courses', function (Blueprint $table): void {
            $table->boolean('grants_role_qualification')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('training_courses', function (Blueprint $table): void {
            $table->dropColumn('grants_role_qualification');
        });
    }
};
