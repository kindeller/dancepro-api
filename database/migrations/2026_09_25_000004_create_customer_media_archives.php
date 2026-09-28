<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_media_archives', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('kind', 16);
            $table->string('event_uuid', 36);
            $table->string('fingerprint', 64);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_key', 1024);
            $table->string('filename', 255);
            $table->unsignedInteger('asset_count');
            $table->unsignedBigInteger('source_bytes')->default(0);
            $table->unsignedBigInteger('archive_bytes')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_download_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'kind', 'event_uuid', 'fingerprint', 'user_id'], 'media_archive_cache_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_media_archives');
    }
};
