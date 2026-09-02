<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_assessment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->decimal('score_percent', 5, 2);
            $table->boolean('passed');
            $table->json('answers');
            $table->json('results');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['training_enrolment_id', 'training_module_id', 'attempt_number'], 'training_attempt_enrolment_module_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assessment_attempts');
    }
};
