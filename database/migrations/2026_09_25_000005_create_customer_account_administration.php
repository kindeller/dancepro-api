<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_media_entitlements', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('expires_at')->index();
            $table->foreignId('revoked_by_user_id')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable()->after('revoked_by_user_id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 24)->default('active')->after('is_active')->index();
            $table->timestamp('paused_at')->nullable()->after('account_status');
            $table->timestamp('cancelled_at')->nullable()->after('paused_at');
            $table->text('account_status_reason')->nullable()->after('cancelled_at');
            $table->foreignId('account_status_changed_by_user_id')->nullable()->after('account_status_reason')->constrained('users')->nullOnDelete();
        });
        Schema::create('customer_account_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 48)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('summary');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('customer_account_emails', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->string('status', 24)->default('queued')->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending_provider')->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->string('provider_refund_id')->nullable()->unique();
            $table->text('reason');
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('order_refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->boolean('revoke_access')->default(true);
            $table->timestamps();
            $table->unique(['order_refund_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refund_items');
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('customer_account_emails');
        Schema::dropIfExists('customer_account_activities');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('account_status_changed_by_user_id');
            $table->dropColumn(['account_status', 'paused_at', 'cancelled_at', 'account_status_reason']);
        });
        Schema::table('order_media_entitlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropColumn(['revoked_at', 'revoke_reason']);
        });
    }
};
