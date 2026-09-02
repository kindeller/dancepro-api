<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_courses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('crew_role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('renewal_of_course_id')->nullable()->constrained('training_courses')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        Schema::create('training_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('module_type');
            $table->longText('content')->nullable();
            $table->string('video_url', 2000)->nullable();
            $table->text('quiz_question')->nullable();
            $table->json('quiz_options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('training_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['training_course_id', 'crew_profile_id'], 'training_enrolment_course_crew_unique');
        });

        Schema::create('training_module_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('selected_option')->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['training_enrolment_id', 'training_module_id'], 'training_progress_enrolment_module_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_module_progress');
        Schema::dropIfExists('training_enrolments');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('training_courses');
    }
};
