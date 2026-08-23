<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_asset_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('storage_disk');
            $table->string('storage_key');
            $table->string('status')->default('active')->index();
            $table->timestamp('became_active_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['media_asset_id', 'status']);
            $table->unique(['storage_disk', 'storage_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_asset_locations');
    }
};
