<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_media_asset_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_media_entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('web');
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedBigInteger('bytes_requested')->default(0);
            $table->timestamp('first_download_at')->nullable();
            $table->timestamp('last_download_at')->nullable();
            $table->string('last_user_agent', 512)->nullable();
            $table->string('last_ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['order_media_entitlement_id', 'media_asset_id'], 'entitlement_asset_download_unique');
            $table->index(['user_id', 'last_download_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_media_asset_downloads');
    }
};
