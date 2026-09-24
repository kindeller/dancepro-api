<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_gallery_accesses', function (Blueprint $table): void {
            $table->unique(['gallery_type', 'scheduling_event_id', 'email_hash'], 'guest_access_event_email_unique');
            $table->unique(['gallery_type', 'concert_id', 'email_hash'], 'guest_access_concert_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('guest_gallery_accesses', function (Blueprint $table): void {
            $table->dropUnique('guest_access_event_email_unique');
            $table->dropUnique('guest_access_concert_email_unique');
        });
    }
};
