<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_director_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->json('feedback')->nullable();
            $table->timestamp('corrections_completed_at')->nullable();
            $table->timestamp('corrections_uploaded_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrected_batch_id')->nullable()->constrained('concert_video_batches')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_batch_id')->nullable()->constrained('concert_video_batches')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->json('workflow_context')->nullable();
            $table->foreignId('photo_release_group_id')->nullable()->constrained('concert_photo_release_groups')->nullOnDelete();
            $table->unique(['photo_release_group_id', 'recipient_email'], 'photo_release_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->dropUnique('photo_release_recipient_unique');
            $table->dropConstrainedForeignId('photo_release_group_id');
            $table->dropColumn('workflow_context');
        });
        Schema::dropIfExists('concert_director_reviews');
    }
};
