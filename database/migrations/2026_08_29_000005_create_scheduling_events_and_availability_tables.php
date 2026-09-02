<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('suburb')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->text('access_notes')->nullable();
            $table->text('parking_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduling_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('name');
            $table->string('event_type')->index();
            $table->date('event_date')->index();
            $table->string('availability_status')->default('draft')->index();
            $table->timestamp('availability_deadline')->nullable()->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduling_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->constrained('scheduling_events')->cascadeOnDelete();
            $table->string('period')->index();
            $table->timestamp('posted_arrival_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('estimated_finish_at')->nullable();
            $table->timestamps();

            $table->unique(['scheduling_event_id', 'period']);
        });

        Schema::create('crew_availability_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_shift_id')->constrained('scheduling_shifts')->cascadeOnDelete();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->string('status')->index();
            $table->text('note')->nullable();
            $table->timestamp('responded_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['scheduling_shift_id', 'crew_profile_id'], 'crew_availability_shift_profile_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_availability_responses');
        Schema::dropIfExists('scheduling_shifts');
        Schema::dropIfExists('scheduling_events');
        Schema::dropIfExists('venues');
    }
};
