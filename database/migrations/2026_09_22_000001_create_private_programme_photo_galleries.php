<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_photo_galleries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('programme_upload_plan_id')->constrained()->restrictOnDelete();
            $table->string('visibility')->default('private');
            $table->timestamps();
        });
        Schema::table('programme_photo_files', function (Blueprint $table): void {
            $table->foreignId('programme_photo_gallery_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('storage_disk')->nullable();
            $table->string('storage_key', 1024)->nullable();
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programme_photo_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('programme_photo_gallery_id');
            $table->dropColumn(['storage_disk', 'storage_key', 'verified_at']);
        });
        Schema::dropIfExists('programme_photo_galleries');
    }
};
