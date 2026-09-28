<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_booking_change_requests', function (Blueprint $table): void {
            $table->json('requested_fields')->nullable();
            $table->text('resolution_note')->nullable();
        });
        Schema::create('concert_booking_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concert_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('studio_booking_change_request_id')->nullable()->constrained(indexName: 'booking_revision_change_fk')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        Schema::table('concert_tracker_email_deliveries', function (Blueprint $table): void {
            $table->string('context_hash', 64)->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concert_tracker_email_deliveries', fn (Blueprint $table) => $table->dropColumn(['context_hash', 'invalidated_at', 'cancellation_reason']));
        Schema::dropIfExists('concert_booking_revisions');
        Schema::table('studio_booking_change_requests', fn (Blueprint $table) => $table->dropColumn(['requested_fields', 'resolution_note']));
    }
};
