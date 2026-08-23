<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('brand_color', 7)->nullable();
        });

        Schema::table('concerts', function (Blueprint $table) {
            $table->string('cover_image_url')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('available_from')->nullable()->index();
            $table->timestamp('available_until')->nullable()->index();
            $table->string('program_url')->nullable();
            $table->string('external_gallery_url')->nullable();
        });

        Schema::table('concert_accesses', function (Blueprint $table) {
            $table->string('student_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('concert_accesses', function (Blueprint $table) {
            $table->dropColumn('student_name');
        });

        Schema::table('concerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn([
                'cover_image_url', 'brand_color', 'is_enabled', 'requires_approval', 'approved_at',
                'available_from', 'available_until', 'program_url', 'external_gallery_url',
            ]);
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn(['description', 'cover_image_url', 'brand_color']);
        });
    }
};
