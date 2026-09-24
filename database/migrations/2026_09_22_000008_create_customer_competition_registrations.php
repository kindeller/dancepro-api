<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_competition_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_event_id')->constrained()->cascadeOnDelete();
            $table->string('registered_code', 100);
            $table->timestamps();
            $table->unique(['user_id', 'scheduling_event_id'], 'customer_competition_registration_identity');
        });
    }

    public function down(): void
    {
        // Removes event registrations only; no customers, media or purchase records.
        Schema::dropIfExists('customer_competition_registrations');
    }
};
