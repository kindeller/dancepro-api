<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_families', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('manager_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('customer_family_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('family_id')->constrained('customer_families')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_dancer_id')->nullable()->unique()->constrained('customer_dancers')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('can_view_media')->default(false);
            $table->boolean('can_purchase')->default(false);
            $table->boolean('is_dancer')->default(false);
            $table->string('status')->default('draft');
            $table->string('invitation_hash', 64)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_family_members');
        Schema::dropIfExists('customer_families');
    }
};
