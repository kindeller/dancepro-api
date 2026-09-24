<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_tracker_alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->nullable()->constrained('scheduling_events', indexName: 'tracker_alert_event_fk')->nullOnDelete();
            $table->foreignId('concert_tracker_task_id')->nullable()->constrained('concert_tracker_tasks', indexName: 'tracker_alert_task_fk')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('dedupe_key');
            $table->string('title');
            $table->text('body');
            $table->string('url');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('digest_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key'], 'tracker_alert_user_dedupe_unique');
            $table->index(['user_id', 'resolved_at', 'read_at'], 'tracker_alert_user_state_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_tracker_alerts');
    }
};
