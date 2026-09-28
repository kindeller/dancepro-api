<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_title_card_packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('sha256', 64);
            $table->string('content_fingerprint', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('item_count');
            $table->json('settings');
            $table->timestamps();
            $table->index(['scheduling_event_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_title_card_packages');
    }
};
