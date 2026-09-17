<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('media_collection_id')->nullable()->constrained('media_collections')->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('snapshot_storage_disk');
            $table->string('snapshot_storage_key');
            $table->string('snapshot_filename')->nullable();
            $table->string('snapshot_display_name')->nullable();
            $table->string('item_type')->default('media')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_amount')->default(0);
            $table->unsignedBigInteger('total_price_amount')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'media_asset_id']);
            $table->index(['snapshot_storage_disk', 'snapshot_storage_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
