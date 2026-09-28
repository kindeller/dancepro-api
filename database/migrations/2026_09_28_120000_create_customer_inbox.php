<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_inbox_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key')->unique();
            $table->string('subject');
            $table->text('body');
            $table->text('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->string('email_status')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'occurred_at']);
        });
        Schema::table('customer_support_requests', function (Blueprint $table): void {
            $table->foreignId('customer_account_email_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('customer_read_at')->nullable();
            $table->timestamp('latest_staff_message_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->index(['user_id', 'last_activity_at']);
        });
        Schema::create('customer_support_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_support_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20);
            $table->string('source_key')->nullable()->unique();
            $table->text('body');
            $table->json('attachment')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_support_messages');
        Schema::table('customer_support_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_account_email_id');
            $table->dropIndex(['user_id', 'last_activity_at']);
            $table->dropColumn(['customer_read_at', 'latest_staff_message_at', 'last_activity_at']);
        });
        Schema::dropIfExists('customer_inbox_notifications');
    }
};
