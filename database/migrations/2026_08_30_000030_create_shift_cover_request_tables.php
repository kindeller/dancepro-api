<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_cover_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_shift_assignment_id')->constrained('scheduling_shift_assignments')->cascadeOnDelete();
            $table->foreignId('requested_by_crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->foreignId('accepted_by_crew_profile_id')->nullable()->constrained('crew_profiles')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->text('message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_cover_request_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_cover_request_id')->constrained('shift_cover_requests')->cascadeOnDelete();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['shift_cover_request_id', 'crew_profile_id'], 'cover_request_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_cover_request_recipients');
        Schema::dropIfExists('shift_cover_requests');
    }
};
