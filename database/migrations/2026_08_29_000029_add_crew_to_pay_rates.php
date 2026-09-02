<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_rates', function (Blueprint $table) {
            $table->dropUnique(['rate_key', 'effective_from']);
            $table->foreignId('crew_profile_id')->nullable()->after('uuid')->constrained('crew_profiles')->cascadeOnDelete();
            $table->unique(['crew_profile_id', 'rate_key', 'effective_from'], 'pay_rates_crew_key_effective_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pay_rates', function (Blueprint $table) {
            $table->dropUnique('pay_rates_crew_key_effective_unique');
            $table->dropConstrainedForeignId('crew_profile_id');
            $table->unique(['rate_key', 'effective_from']);
        });
    }
};
