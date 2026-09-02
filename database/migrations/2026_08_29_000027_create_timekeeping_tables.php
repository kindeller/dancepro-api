<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_shift_assignment_id')->unique()->constrained('scheduling_shift_assignments', indexName: 'time_entry_assignment_fk')->cascadeOnDelete();
            $table->timestamp('actual_clock_in_at')->nullable();
            $table->timestamp('clock_in_recorded_at')->nullable();
            $table->string('clock_in_source')->nullable();
            $table->timestamp('payable_start_at')->nullable();
            $table->timestamp('actual_finish_at')->nullable();
            $table->timestamp('finish_recorded_at')->nullable();
            $table->string('finish_source')->nullable();
            $table->text('optional_note')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_time_entry_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_time_entry_id')->constrained('assignment_time_entries')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('field');
            $table->timestamp('old_value')->nullable();
            $table->timestamp('new_value')->nullable();
            $table->text('optional_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_time_entry_audits');
        Schema::dropIfExists('assignment_time_entries');
    }
};
