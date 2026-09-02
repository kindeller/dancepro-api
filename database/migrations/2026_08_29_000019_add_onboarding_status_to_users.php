<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('invitation_sent_at')->nullable()->after('email_verified_at');
            $table->timestamp('onboarding_completed_at')->nullable()->after('invitation_sent_at');
        });

        DB::table('users')->where('type', 'crew')->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['invitation_sent_at', 'onboarding_completed_at']);
        });
    }
};
