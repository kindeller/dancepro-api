<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id');
            $table->string('platform', 20);
            $table->text('push_token');
            $table->string('push_token_hash', 64)->index();
            $table->string('environment', 20);
            $table->string('app_version', 50);
            $table->string('device_name');
            $table->timestamp('last_registered_at');
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'installation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_devices');
    }
};
