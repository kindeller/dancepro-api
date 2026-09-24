<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_conversation_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('studio_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('studio_conversation_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_conversation_message_id')->constrained(indexName: 'studio_attachment_message_fk')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('storage_key');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_conversation_attachments');
        Schema::dropIfExists('studio_conversation_threads');
    }
};
