<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->boolean('family_sharing_enabled')->default(true);
            $table->boolean('family_downloads_enabled')->default(true);
        });
        Schema::create('concert_family_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name', 100);
            $table->string('recipient_email');
            $table->string('recipient_hash', 64);
            $table->timestamp('contact_redacted_at')->nullable();
            $table->timestamp('invitation_expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('link_count')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->string('code_hash')->nullable();
            $table->string('challenge_session_hash', 64)->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->string('verified_session_hash', 64)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('player_hash', 64)->nullable();
            $table->timestamp('player_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['concert_id', 'user_id', 'recipient_hash'], 'concert_family_recipient_unique');
        });
        Schema::create('concert_family_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concert_family_share_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('download_requests')->default(0);
            $table->unsignedInteger('playback_starts')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamp('playback_completed_at')->nullable();
            $table->timestamps();
            $table->unique(['concert_family_share_id', 'media_asset_id'], 'concert_family_video_unique');
        });
        Schema::create('concert_family_downloads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_family_video_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_uuid');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['concert_family_video_id', 'request_uuid'], 'concert_family_download_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_family_downloads');
        Schema::dropIfExists('concert_family_videos');
        Schema::dropIfExists('concert_family_shares');
        Schema::table('studios', fn (Blueprint $table) => $table->dropColumn(['family_sharing_enabled', 'family_downloads_enabled']));
    }
};
