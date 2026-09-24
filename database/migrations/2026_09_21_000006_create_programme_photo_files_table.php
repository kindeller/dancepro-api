<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_photo_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_photo_contribution_id')->constrained()->restrictOnDelete();
            $table->uuid('client_photo_uuid');
            $table->string('file_name');
            $table->unsignedBigInteger('byte_count');
            $table->char('sha256', 64);
            $table->boolean('is_top_pic')->default(false);
            $table->string('status')->default('reserved');
            $table->timestamps();
            $table->unique(['programme_photo_contribution_id', 'client_photo_uuid', 'sha256'], 'programme_photo_file_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_photo_files');
    }
};
