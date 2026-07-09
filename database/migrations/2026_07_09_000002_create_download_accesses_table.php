<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('download_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_link_id')->constrained('download_links')->cascadeOnDelete();
            $table->timestamp('accessed_at')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->boolean('was_successful')->default(false)->index();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('download_link_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_accesses');
    }
};
