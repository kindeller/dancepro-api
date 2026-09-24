<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_commerce_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('settings');
            $table->timestamps();
        });
        Schema::create('media_discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('percent');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_discount_codes');
        Schema::dropIfExists('media_commerce_settings');
    }
};
