<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_extractions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_source_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('draft_version');
            $table->string('status', 20)->default('queued');
            $table->json('result')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['programme_id', 'programme_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_extractions');
    }
};
