<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('shirt_size')->nullable();
            $table->string('jacket_size')->nullable();
            $table->date('commencement_date')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crew_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('event_type')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('crew_role_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->string('status')->default('approved')->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['crew_profile_id', 'crew_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_role_qualifications');
        Schema::dropIfExists('crew_roles');
        Schema::dropIfExists('crew_profiles');
    }
};
