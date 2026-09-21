<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_ingest_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('action')->index();
            $table->boolean('was_successful')->default(true);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['media_asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_ingest_events');
    }
};
