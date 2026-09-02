<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table): void {
            $table->string('submission_fingerprint', 64)->nullable()->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('concert_bookings', function (Blueprint $table): void {
            $table->dropColumn('submission_fingerprint');
        });
    }
};
