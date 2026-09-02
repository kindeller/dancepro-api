<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->string('roster_status')->default('draft')->index()->after('availability_deadline');
            $table->timestamp('roster_published_at')->nullable()->after('roster_status');
        });

        Schema::create('scheduling_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_shift_id')->constrained('scheduling_shifts')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('acknowledgement_status')->default('not_acknowledged')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['scheduling_shift_id', 'crew_role_id'], 'shift_role_assignment_unique');
            $table->unique(['scheduling_shift_id', 'crew_profile_id'], 'shift_crew_assignment_unique');
        });

        Schema::create('crew_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('scheduling_event_crew_assignments');
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_notifications');
        Schema::dropIfExists('scheduling_shift_assignments');
        Schema::table('scheduling_events', function (Blueprint $table) {
            $table->dropColumn(['roster_status', 'roster_published_at']);
        });
    }
};
