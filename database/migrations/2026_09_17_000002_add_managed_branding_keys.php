<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->string('cover_image_storage_key')->nullable();
            $table->uuid('cover_image_revision')->nullable();
            $table->string('cover_image_mime_type', 50)->nullable();
        });
        Schema::table('concerts', function (Blueprint $table): void {
            $table->string('cover_image_storage_key')->nullable();
            $table->uuid('cover_image_revision')->nullable();
            $table->string('cover_image_mime_type', 50)->nullable();
            $table->string('program_storage_key')->nullable();
            $table->uuid('program_revision')->nullable();
            $table->string('program_mime_type', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concerts', function (Blueprint $table): void {
            $table->dropColumn(['cover_image_storage_key', 'cover_image_revision', 'cover_image_mime_type', 'program_storage_key', 'program_revision', 'program_mime_type']);
        });
        Schema::table('studios', function (Blueprint $table): void {
            $table->dropColumn(['cover_image_storage_key', 'cover_image_revision', 'cover_image_mime_type']);
        });
    }
};
