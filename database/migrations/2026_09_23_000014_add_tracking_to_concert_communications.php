<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->string('provider_message_id')->nullable()->after('failure_message')->index();
            $table->unsignedInteger('open_count')->default(0)->after('provider_message_id');
            $table->timestamp('first_opened_at')->nullable()->after('open_count');
            $table->timestamp('last_opened_at')->nullable()->after('first_opened_at');
        });

        Schema::create('concert_tracker_inbound_emails', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->nullable()->constrained('scheduling_events', indexName: 'tracker_inbound_event_fk')->nullOnDelete();
            $table->foreignId('reply_to_delivery_id')->nullable()->constrained('concert_tracker_email_deliveries', indexName: 'tracker_inbound_delivery_fk')->nullOnDelete();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('sender_name')->nullable();
            $table->string('sender_email');
            $table->string('recipient_email');
            $table->string('subject');
            $table->longText('body');
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['scheduling_event_id', 'received_at'], 'tracker_inbound_event_received_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_tracker_inbound_emails');
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['provider_message_id', 'open_count', 'first_opened_at', 'last_opened_at']);
        });
    }
};
