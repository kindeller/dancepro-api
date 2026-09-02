<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method')->default('manual');
            $table->text('note')->nullable();
            $table->timestamp('reminded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_reminders');
    }
};
