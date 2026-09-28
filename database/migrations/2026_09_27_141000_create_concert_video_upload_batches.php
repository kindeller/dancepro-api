<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_video_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('manifest_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users');
            $table->json('studio_inclusions')->nullable();
            $table->timestamps();
        });
        Schema::create('concert_video_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_video_batch_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->unsignedBigInteger('byte_count');
            $table->string('sha256', 64);
            $table->string('storage_disk');
            $table->string('storage_key', 1024);
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_video_files');
        Schema::dropIfExists('concert_video_batches');
    }
};
