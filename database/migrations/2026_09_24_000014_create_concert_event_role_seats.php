<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_event_role_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained()->cascadeOnDelete();
            $table->json('seats');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'crew_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_event_role_seats');
    }
};
