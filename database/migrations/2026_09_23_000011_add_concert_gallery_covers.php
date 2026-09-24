<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_collections', function (Blueprint $table): void {
            $table->foreignId('cover_media_asset_id')->nullable()->after('media_price_list_id')
                ->constrained('media_assets')->nullOnDelete();
        });
        Schema::table('concert_photo_release_groups', function (Blueprint $table): void {
            $table->foreignId('cover_media_asset_id')->nullable()->after('media_watermark_id')
                ->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('concert_photo_release_groups', fn (Blueprint $table) => $table->dropConstrainedForeignId('cover_media_asset_id'));
        Schema::table('media_collections', fn (Blueprint $table) => $table->dropConstrainedForeignId('cover_media_asset_id'));
    }
};
