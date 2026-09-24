<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_routine_studios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('item_uuid');
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scheduling_event_id', 'item_uuid'], 'competition_routine_studio_identity');
            $table->index(['scheduling_event_id', 'studio_id']);
        });
        Schema::create('competition_routine_studio_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('item_uuid');
            $table->foreignId('before_studio_id')->nullable()->constrained('studios')->nullOnDelete();
            $table->foreignId('after_studio_id')->nullable()->constrained('studios')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_routine_studio_changes');
        Schema::dropIfExists('competition_routine_studios');
    }
};
