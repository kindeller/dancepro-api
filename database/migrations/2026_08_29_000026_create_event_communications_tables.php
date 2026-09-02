<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained('scheduling_events')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->string('message_type')->default('discussion')->index();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();
        });

        Schema::create('event_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_message_id')->constrained('event_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['event_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_message_reads');
        Schema::dropIfExists('event_messages');
    }
};
