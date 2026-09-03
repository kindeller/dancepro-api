<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_notifications', function (Blueprint $table): void {
            $table->string('destination_type', 40)->nullable()->after('message');
            $table->uuid('destination_id')->nullable()->after('destination_type');
        });
    }

    public function down(): void
    {
        Schema::table('crew_notifications', function (Blueprint $table): void {
            $table->dropColumn(['destination_type', 'destination_id']);
        });
    }
};
