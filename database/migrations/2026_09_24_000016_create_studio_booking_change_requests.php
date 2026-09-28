<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_booking_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->string('change_type', 40);
            $table->text('requested_value');
            $table->text('details')->nullable();
            $table->string('status', 30)->default('requested');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['scheduling_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_booking_change_requests');
    }
};
