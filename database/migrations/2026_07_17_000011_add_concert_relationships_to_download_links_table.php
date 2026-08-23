<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_links', function (Blueprint $table) {
            $table->foreignId('concert_id')->nullable()->constrained('concerts')->nullOnDelete();
            $table->foreignId('media_collection_id')->nullable()->constrained('media_collections')->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            $table->index(['concert_id', 'status']);
            $table->index(['media_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('download_links', function (Blueprint $table) {
            $table->dropIndex(['concert_id', 'status']);
            $table->dropIndex(['media_asset_id', 'status']);
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropConstrainedForeignId('media_asset_id');
            $table->dropConstrainedForeignId('media_collection_id');
            $table->dropConstrainedForeignId('concert_id');
        });
    }
};
