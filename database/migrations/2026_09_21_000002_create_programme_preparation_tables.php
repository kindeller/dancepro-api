<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduling_event_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('draft_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('programme_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('programmes', function (Blueprint $table): void {
            $table->foreignId('programme_source_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('programme_nodes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->json('data');
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('programme_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_source_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->unique(['programme_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_revisions');
        Schema::dropIfExists('programme_nodes');
        Schema::table('programmes', fn (Blueprint $table) => $table->dropConstrainedForeignId('programme_source_id'));
        Schema::dropIfExists('programme_sources');
        Schema::dropIfExists('programmes');
    }
};
