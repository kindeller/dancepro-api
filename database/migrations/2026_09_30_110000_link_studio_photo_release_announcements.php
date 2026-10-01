<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_conversation_messages', function (Blueprint $table): void {
            $table->foreignId('photo_release_group_id')->nullable()->unique()->constrained('concert_photo_release_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('studio_conversation_messages', function (Blueprint $table): void {
            $table->dropForeign(['photo_release_group_id']);
            $table->dropUnique(['photo_release_group_id']);
            $table->dropColumn('photo_release_group_id');
        });
    }
};
