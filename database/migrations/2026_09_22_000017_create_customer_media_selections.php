<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_media_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->string('selection_key', 100);
            $table->string('product', 24);
            $table->uuid('item_uuid');
            $table->uuid('photo_uuid')->nullable();
            $table->boolean('is_favourite')->default(false);
            $table->boolean('in_cart')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'scheduling_event_id', 'selection_key'], 'customer_media_selection_unique');
            $table->index(['user_id', 'in_cart']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_media_selections');
    }
};
