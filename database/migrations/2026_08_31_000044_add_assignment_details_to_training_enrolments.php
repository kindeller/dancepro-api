<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_enrolments', function (Blueprint $table): void {
            $table->foreignId('assigned_by_user_id')->nullable()->after('crew_profile_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('status');
            $table->date('due_at')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('training_enrolments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropColumn(['assigned_at', 'due_at']);
        });
    }
};
