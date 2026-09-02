<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_shifts', function (Blueprint $table) {
            $table->index('scheduling_event_id', 'scheduling_shifts_event_fk_index');
        });

        Schema::table('scheduling_shifts', function (Blueprint $table) {
            $table->dropUnique(['scheduling_event_id', 'period']);
            $table->date('shift_date')->nullable()->after('period');
            $table->boolean('requires_setup')->default(false);
            $table->boolean('requires_set_down')->default(false);
            $table->unique(['scheduling_event_id', 'shift_date', 'period'], 'scheduling_shift_event_date_period_unique');
        });

        DB::table('scheduling_shifts')->join('scheduling_events', 'scheduling_events.id', '=', 'scheduling_shifts.scheduling_event_id')
            ->update(['scheduling_shifts.shift_date' => DB::raw('scheduling_events.event_date')]);

        Schema::create('scheduling_shift_role_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_shift_id')->constrained('scheduling_shifts')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['scheduling_shift_id', 'crew_role_id'], 'shift_role_requirement_unique');
        });

        Schema::create('concert_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('pending')->index();
            $table->string('studio_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->boolean('wants_portrait_photography')->default(false);
            $table->boolean('wants_concert_photography')->default(true);
            $table->boolean('wants_concert_videography')->default(false);
            $table->json('concert_inclusions')->nullable();
            $table->string('multiple_concert_relationship')->nullable();
            $table->unsignedInteger('approximate_family_count')->nullable();
            $table->text('postal_address')->nullable();
            $table->text('previous_video_feedback')->nullable();
            $table->json('accepted_requirements')->nullable();
            $table->text('internal_review_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('concert_booking_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_booking_id')->constrained('concert_bookings')->cascadeOnDelete();
            $table->string('item_type')->index();
            $table->string('title')->nullable();
            $table->string('venue_name');
            $table->text('venue_address');
            $table->date('event_date');
            $table->time('starts_at');
            $table->time('finishes_at');
            $table->foreignId('scheduling_event_id')->nullable()->constrained('scheduling_events')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_booking_items');
        Schema::dropIfExists('concert_bookings');
        Schema::dropIfExists('scheduling_shift_role_requirements');
        Schema::table('scheduling_shifts', function (Blueprint $table) {
            $table->dropUnique('scheduling_shift_event_date_period_unique');
            $table->dropColumn(['shift_date', 'requires_setup', 'requires_set_down']);
            $table->unique(['scheduling_event_id', 'period']);
            $table->dropIndex('scheduling_shifts_event_fk_index');
        });
    }
};
