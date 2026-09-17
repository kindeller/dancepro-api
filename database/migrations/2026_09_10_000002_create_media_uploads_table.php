<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->index();
            $table->string('status')->default('pending')->index();
            $table->uuid('idempotency_key')->nullable();
            $table->string('relative_path')->nullable();
            $table->string('storage_disk');
            $table->string('storage_key')->nullable();
            $table->text('provider_upload_id')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum_algorithm')->nullable();
            $table->string('checksum')->nullable();
            $table->json('files')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('aborted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key', 'kind'], 'media_uploads_user_idempotency_kind_unique');
            $table->index(['media_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
