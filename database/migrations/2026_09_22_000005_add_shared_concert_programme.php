<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->foreignId('shared_upload_plan_id')->nullable()->constrained('programme_upload_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programmes', fn (Blueprint $table) => $table->dropConstrainedForeignId('shared_upload_plan_id'));
    }
};
