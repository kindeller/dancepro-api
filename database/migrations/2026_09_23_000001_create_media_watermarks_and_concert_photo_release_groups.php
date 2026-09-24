<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_watermarks')) {
            Schema::create('media_watermarks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('gallery_type', 20);
                $table->string('mode', 10);
                $table->string('text')->nullable();
                $table->string('storage_disk')->nullable();
                $table->string('storage_key')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['gallery_type', 'is_default']);
            });
        }
        if (! Schema::hasColumn('competition_gallery_settings', 'media_watermark_id')) {
            Schema::table('competition_gallery_settings', function (Blueprint $table): void {
                $table->foreignId('media_watermark_id')->nullable()->after('cover_photo_uuid')
                    ->constrained('media_watermarks')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('media_collections', 'media_watermark_id')) {
            Schema::table('media_collections', function (Blueprint $table): void {
                $table->foreignId('media_watermark_id')->nullable()->after('competition_id')
                    ->constrained('media_watermarks')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('concert_photo_release_groups')) {
            Schema::create('concert_photo_release_groups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->foreignId('media_watermark_id')->nullable()->constrained('media_watermarks')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('concert_photo_release_group_collections')) {
            Schema::create('concert_photo_release_group_collections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('concert_photo_release_group_id');
                $table->unsignedBigInteger('media_collection_id');
                $table->timestamps();
                $table->unique('media_collection_id', 'photo_release_collection_unique');
                $table->foreign('concert_photo_release_group_id', 'photo_release_group_fk')
                    ->references('id')->on('concert_photo_release_groups')->cascadeOnDelete();
                $table->foreign('media_collection_id', 'photo_release_collection_fk')
                    ->references('id')->on('media_collections')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('concert_photo_release_group_changes')) {
            Schema::create('concert_photo_release_group_changes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('concert_photo_release_group_id');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 30);
                $table->json('details')->nullable();
                $table->timestamp('created_at');
                $table->foreign('concert_photo_release_group_id', 'photo_release_change_group_fk')
                    ->references('id')->on('concert_photo_release_groups')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_photo_release_group_changes');
        Schema::dropIfExists('concert_photo_release_group_collections');
        Schema::dropIfExists('concert_photo_release_groups');
        Schema::table('media_collections', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_watermark_id'));
        Schema::table('competition_gallery_settings', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_watermark_id'));
        Schema::dropIfExists('media_watermarks');
    }
};
