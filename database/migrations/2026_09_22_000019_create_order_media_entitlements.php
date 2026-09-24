<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_media_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 16);
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('first_download_at')->nullable();
            $table->timestamp('last_download_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->unique(['order_item_id', 'media_type'], 'order_item_media_type_unique');
            $table->index('expires_at');
        });
        Schema::create('order_media_entitlement_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_media_entitlement_id');
            $table->foreignId('media_asset_id');
            $table->foreign('order_media_entitlement_id', 'entitlement_assets_entitlement_fk')
                ->references('id')->on('order_media_entitlements')->cascadeOnDelete();
            $table->foreign('media_asset_id', 'entitlement_assets_media_fk')
                ->references('id')->on('media_assets')->restrictOnDelete();
            $table->unique(['order_media_entitlement_id', 'media_asset_id'], 'order_entitlement_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_media_entitlement_assets');
        Schema::dropIfExists('order_media_entitlements');
    }
};
