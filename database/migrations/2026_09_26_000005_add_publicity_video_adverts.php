<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicity_slides', function (Blueprint $table): void {
            $table->string('video_key')->nullable();
            $table->string('video_hash', 64)->nullable();
            $table->unsignedInteger('video_bytes')->nullable();
            $table->unsignedSmallInteger('video_seconds')->nullable();
            $table->boolean('play_sound')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('publicity_slides', fn (Blueprint $table) => $table->dropColumn(['video_key', 'video_hash', 'video_bytes', 'video_seconds', 'play_sound']));
    }
};
