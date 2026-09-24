<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_concert_photo_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_favourite')->default(false);
            $table->boolean('in_cart')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'media_asset_id'], 'customer_concert_photo_selection_unique');
            $table->index(['user_id', 'studio_id', 'in_cart'], 'concert_photo_cart_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_concert_photo_selections');
    }
};
