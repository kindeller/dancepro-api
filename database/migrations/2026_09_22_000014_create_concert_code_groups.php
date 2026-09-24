<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concert_code_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('code_hash');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['studio_id', 'year', 'name']);
        });

        Schema::table('concerts', function (Blueprint $table) {
            $table->foreignId('concert_code_group_id')->nullable()->constrained('concert_code_groups')->nullOnDelete();
        });
        Schema::table('concert_access_grants', function (Blueprint $table) {
            $table->foreignId('concert_code_group_id')->nullable()->constrained('concert_code_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('concert_access_grants', fn (Blueprint $table) => $table->dropConstrainedForeignId('concert_code_group_id'));
        Schema::table('concerts', fn (Blueprint $table) => $table->dropConstrainedForeignId('concert_code_group_id'));
        Schema::dropIfExists('concert_code_groups');
    }
};
