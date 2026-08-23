<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('preferred_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('registration_source')->nullable()->index();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
