<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->text('waiver_reason')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Removing a waiver must never turn it into completion.
        DB::table('programme_photo_contributions')->where('status', 'waived')
            ->update(['status' => 'receiving']);
        Schema::table('programme_photo_contributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('waived_by_user_id');
            $table->dropColumn(['waiver_reason', 'waived_at']);
        });
    }
};
