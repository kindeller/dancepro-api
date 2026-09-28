<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_slideshows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('photo_seconds')->default(8);
            $table->unsignedSmallInteger('promotion_every')->default(5);
            $table->timestamps();
        });
        Schema::create('publicity_slides', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('image_key')->nullable();
            $table->string('image_hash', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('duration_seconds')->default(12);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('tv_screens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100)->default('New screen');
            $table->string('token_hash', 64)->unique();
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('token_expires_at');
            $table->foreignId('scheduling_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paired_by')->nullable()->constrained('users');
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('cached_revision', 64)->nullable();
            $table->unsignedInteger('cached_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_screens');
        Schema::dropIfExists('publicity_slides');
        Schema::dropIfExists('tv_slideshows');
    }
};
