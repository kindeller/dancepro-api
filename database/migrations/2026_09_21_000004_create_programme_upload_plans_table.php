<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_upload_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('digest', 64);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['programme_id', 'digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_upload_plans');
    }
};
