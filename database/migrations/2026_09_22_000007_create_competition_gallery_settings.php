<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_gallery_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_title')->nullable();
            $table->uuid('cover_photo_uuid')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::create('competition_gallery_setting_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Presentation/history only; no events, media, programmes or release state are removed.
        Schema::dropIfExists('competition_gallery_setting_changes');
        Schema::dropIfExists('competition_gallery_settings');
    }
};
