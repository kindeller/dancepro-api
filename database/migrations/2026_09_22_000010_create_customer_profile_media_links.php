<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_dancers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
        Schema::create('customer_studios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'studio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_studios');
        Schema::dropIfExists('customer_dancers');
    }
};
