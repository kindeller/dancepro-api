<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropColumn('completed_at');
        });
    }
};
