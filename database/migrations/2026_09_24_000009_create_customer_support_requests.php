<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_support_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_media_entitlement_id')->nullable();
            $table->foreign('order_media_entitlement_id', 'support_request_entitlement_fk')
                ->references('id')->on('order_media_entitlements')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 30)->default('pending')->index();
            $table->json('context')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_media_entitlement_extension_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_media_entitlement_id');
            $table->foreign('order_media_entitlement_id', 'entitlement_extension_entitlement_fk')
                ->references('id')->on('order_media_entitlements')->cascadeOnDelete();
            $table->foreignId('customer_support_request_id')->nullable();
            $table->foreign('customer_support_request_id', 'entitlement_extension_request_fk')
                ->references('id')->on('customer_support_requests')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('previous_expires_at')->nullable();
            $table->timestamp('new_expires_at');
            $table->unsignedSmallInteger('extension_days');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_media_entitlement_extension_audits');
        Schema::dropIfExists('customer_support_requests');
    }
};
