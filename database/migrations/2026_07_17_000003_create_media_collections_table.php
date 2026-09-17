<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concert_id')->nullable()->constrained('concerts')->cascadeOnDelete();
            $table->unsignedBigInteger('competition_id')->nullable()->index();
            $table->string('name');
            $table->string('media_type')->index();
            $table->string('catalogue_mode')->default('storage')->index();
            $table->string('status')->default('draft')->index();
            $table->string('visibility')->default('private')->index();
            $table->string('storage_disk');
            $table->string('storage_prefix');
            $table->string('manifest_key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['concert_id', 'media_type']);
            $table->index(['competition_id', 'media_type']);
            $table->index(['storage_disk', 'storage_prefix']);
        });

        DB::statement('ALTER TABLE media_collections ADD CONSTRAINT media_collections_exactly_one_owner CHECK ((concert_id IS NOT NULL AND competition_id IS NULL) OR (concert_id IS NULL AND competition_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('media_collections');
    }
};
