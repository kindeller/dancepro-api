<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('merged_into_user_id')->nullable()->after('account_status_changed_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable()->after('cancelled_at');
        });
        Schema::create('customer_consent_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32);
            $table->boolean('granted');
            $table->string('source', 48);
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('customer_login_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('channel', 24);
            $table->boolean('successful');
            $table->ipAddress('ip_address')->nullable();
            $table->string('device_name')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('risk_level', 16)->default('normal');
            $table->json('risk_reasons')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['email', 'occurred_at']);
        });
        Schema::create('customer_engagement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_kind', 24);
            $table->uuid('event_uuid');
            $table->string('action', 24);
            $table->string('subject_uuid')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_kind', 'event_uuid', 'action']);
        });
        Schema::create('customer_account_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->json('tags')->nullable();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('customer_risk_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 24)->default('open');
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('details');
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('status', 24)->default('pending');
            $table->text('reason');
            $table->string('export_path')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_account_merges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('merged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('summary');
            $table->timestamp('merged_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_merges');
        Schema::dropIfExists('customer_privacy_requests');
        Schema::dropIfExists('customer_risk_events');
        Schema::dropIfExists('customer_account_notes');
        Schema::dropIfExists('customer_engagement_events');
        Schema::dropIfExists('customer_login_events');
        Schema::dropIfExists('customer_consent_histories');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_user_id');
            $table->dropColumn('anonymized_at');
        });
    }
};
