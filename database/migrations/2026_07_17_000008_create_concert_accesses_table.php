<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concert_id')->constrained('concerts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('concert_access_grant_id')->nullable()->constrained('concert_access_grants')->nullOnDelete();
            $table->string('access_method')->index();
            $table->timestamp('accessed_at')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('session_identifier')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->boolean('was_successful')->default(true)->index();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['concert_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concert_accesses');
    }
};
