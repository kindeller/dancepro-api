<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('media_collection_id')->constrained('media_collections')->cascadeOnDelete();
            $table->string('media_type')->index();
            $table->string('storage_disk');
            $table->string('storage_key');
            $table->string('original_filename')->nullable();
            $table->string('display_name')->nullable();
            $table->string('status')->default('available')->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable()->index();
            $table->string('thumbnail_storage_disk')->nullable();
            $table->string('thumbnail_storage_key')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('missing_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['storage_disk', 'storage_key']);
            $table->index(['media_collection_id', 'sort_order']);
            $table->index(['media_collection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
