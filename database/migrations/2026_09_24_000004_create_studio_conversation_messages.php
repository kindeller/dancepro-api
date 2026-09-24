<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->nullable()->constrained('scheduling_events')->nullOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 24);
            $table->string('channel', 24)->default('web');
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamp('staff_read_at')->nullable();
            $table->timestamp('studio_read_at')->nullable();
            $table->timestamps();
            $table->index(['studio_id', 'sent_at']);
            $table->index(['direction', 'staff_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_conversation_messages');
    }
};
