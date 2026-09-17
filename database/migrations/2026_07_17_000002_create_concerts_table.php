<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_id')->constrained('studios')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->date('event_date')->nullable()->index();
            $table->date('event_end_date')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('description')->nullable();
            $table->string('storage_disk')->default('s3_concerts');
            $table->string('storage_prefix');
            $table->string('access_password_hash')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['studio_id', 'status']);
            $table->index(['storage_disk', 'storage_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concerts');
    }
};
