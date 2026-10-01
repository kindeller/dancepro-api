<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_media_asset_downloads', function (Blueprint $table): void {
            $table->unique(['order_media_entitlement_id', 'media_asset_id', 'user_id'], 'entitlement_asset_viewer_unique');
            $table->dropUnique('entitlement_asset_download_unique');
        });
    }

    public function down(): void
    {
        // Keep per-viewer history intact: an ambiguous rollback must be explicitly resolved.
        if (DB::table('order_media_asset_downloads')->select('order_media_entitlement_id', 'media_asset_id')
            ->groupBy('order_media_entitlement_id', 'media_asset_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Per-viewer download history exists. Archive/merge it before reverting family download tracking.');
        }
        Schema::table('order_media_asset_downloads', function (Blueprint $table): void {
            $table->unique(['order_media_entitlement_id', 'media_asset_id'], 'entitlement_asset_download_unique');
            $table->dropUnique('entitlement_asset_viewer_unique');
        });
    }
};
