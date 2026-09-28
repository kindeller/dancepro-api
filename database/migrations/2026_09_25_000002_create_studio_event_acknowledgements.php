<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_event_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->string('requirement', 80);
            $table->foreignId('completed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'requirement'], 'studio_event_requirement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_event_acknowledgements');
    }
};
