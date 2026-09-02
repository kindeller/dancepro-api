<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('participant_key')->unique();
            $table->timestamps();
        });

        Schema::create('direct_chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['direct_chat_conversation_id', 'user_id'], 'direct_chat_participant_unique');
        });

        Schema::create('direct_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('direct_chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['direct_chat_conversation_id', 'created_at'], 'direct_chat_message_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_chat_messages');
        Schema::dropIfExists('direct_chat_participants');
        Schema::dropIfExists('direct_chat_conversations');
    }
};
