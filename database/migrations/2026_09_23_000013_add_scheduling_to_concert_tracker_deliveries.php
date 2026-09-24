<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->foreignId('scheduling_event_id')->nullable()->after('uuid')->constrained('scheduling_events', indexName: 'tracker_delivery_event_fk')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users', indexName: 'tracker_delivery_cancelled_by_fk')->nullOnDelete();
            $table->index(['scheduling_event_id', 'created_at'], 'tracker_delivery_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->dropIndex('tracker_delivery_event_created_index');
            $table->dropForeign('tracker_delivery_cancelled_by_fk');
            $table->dropForeign('tracker_delivery_event_fk');
            $table->dropColumn(['scheduling_event_id', 'scheduled_at', 'cancelled_at', 'cancelled_by_user_id']);
        });
    }
};
