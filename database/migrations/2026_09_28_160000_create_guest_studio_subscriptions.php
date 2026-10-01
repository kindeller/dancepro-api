<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_studio_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('email_hash', 64);
            $table->text('email');
            $table->timestamp('consent_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['studio_id', 'email_hash'], 'guest_studio_subscription_unique');
        });
        Schema::table('guest_gallery_registrations', function (Blueprint $table): void {
            $table->foreignId('guest_studio_subscription_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guest_gallery_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_studio_subscription_id');
        });
        Schema::dropIfExists('guest_studio_subscriptions');
    }
};
