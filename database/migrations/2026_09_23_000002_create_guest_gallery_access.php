<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_gallery_registrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->char('email_hash', 64);
            $table->text('email');
            $table->timestamp('consent_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'email_hash'], 'guest_gallery_registration_unique');
            $table->index(['notified_at', 'unsubscribed_at'], 'guest_gallery_notification_pending');
        });

        Schema::create('guest_gallery_accesses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('gallery_type', 20);
            $table->foreignId('scheduling_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('concert_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('email_hash', 64);
            $table->text('email');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['gallery_type', 'scheduling_event_id']);
            $table->index(['gallery_type', 'concert_id']);
        });

        Schema::create('guest_gallery_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_gallery_access_id')->constrained('guest_gallery_accesses')->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('concert_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('selection_key', 100);
            $table->string('product', 24);
            $table->uuid('item_uuid')->nullable();
            $table->uuid('photo_uuid')->nullable();
            $table->boolean('is_favourite')->default(false);
            $table->boolean('in_cart')->default(false);
            $table->timestamps();
            $table->unique(['guest_gallery_access_id', 'selection_key'], 'guest_gallery_selection_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_gallery_selections');
        Schema::dropIfExists('guest_gallery_accesses');
        Schema::dropIfExists('guest_gallery_registrations');
    }
};
