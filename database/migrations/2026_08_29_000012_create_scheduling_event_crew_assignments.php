<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_event_crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_event_id')->constrained('scheduling_events')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scheduling_event_id', 'crew_role_id'], 'event_role_assignment_unique');
            $table->unique(['scheduling_event_id', 'crew_profile_id'], 'event_crew_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_event_crew_assignments');
    }
};
