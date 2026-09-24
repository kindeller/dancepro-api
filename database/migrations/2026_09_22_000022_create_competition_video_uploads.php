<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_video_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_upload_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('status', 20)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('competition_video_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('competition_video_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('item_uuid')->nullable()->index();
            $table->string('original_filename');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('byte_count');
            $table->string('storage_disk');
            $table->string('storage_key');
            $table->string('status', 20)->default('reserved');
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'sha256']);
        });
        Schema::create('competition_video_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('item_uuid');
            $table->boolean('is_held')->default(true);
            $table->foreignId('updated_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'item_uuid']);
        });
        Schema::create('competition_video_hold_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('item_uuid');
            $table->boolean('is_held');
            $table->foreignId('user_id')->constrained();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_video_hold_changes');
        Schema::dropIfExists('competition_video_holds');
        Schema::dropIfExists('competition_video_files');
        Schema::dropIfExists('competition_video_batches');
    }
};
